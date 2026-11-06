<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Reservation;
use App\Models\Staff;
use App\Models\User;
use App\Services\BookingService;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

/**
 * お客さん用の予約ページ(ログイン不要)
 *  1. メニューを選ぶ → 2. 担当者と日時を選ぶ → 3. 名前と電話番号を入れる → 4. 予約完了(専用URL)
 */
class BookingController extends Controller
{
    public function __construct(private SlotFinder $finder, private BookingService $booking) {}

    public function shop(string $slug): View
    {
        $shop = $this->shopOf($slug);

        return view('booking.shop', [
            'shop' => $shop,
            'menus' => $shop->menus()->active()->ordered()->get(),
        ]);
    }

    public function times(Request $request, string $slug, int $menu): View
    {
        $shop = $this->shopOf($slug);
        $menu = $this->menuOf($shop, $menu);
        $candidates = $this->finder->candidates($shop, $menu);
        $nominated = $this->nominated($request, $candidates);

        [$first, $last] = $this->finder->window($shop);
        $days = $this->finder->range($shop, $menu, $nominated, $first, $last);

        $date = $this->dateParam($request->query('date'));
        if (! $date || ! array_key_exists($date->toDateString(), $days)) {
            // 日付の指定がなければ、いちばん近い空きのある日を開く
            $firstOpen = collect($days)->filter()->keys()->first();
            $date = $firstOpen ? CarbonImmutable::parse($firstOpen) : $first;
        }

        return view('booking.times', [
            'shop' => $shop,
            'menu' => $menu,
            'candidates' => $candidates,
            'nominated' => $nominated,
            'days' => $days,
            'first' => $first,
            'last' => $last,
            'date' => $date,
            'slots' => $days[$date->toDateString()] ?? [],
        ]);
    }

    public function form(Request $request, string $slug, int $menu): View|RedirectResponse
    {
        $shop = $this->shopOf($slug);
        $menu = $this->menuOf($shop, $menu);
        $nominated = $this->nominated($request, $this->finder->candidates($shop, $menu));
        $start = $this->startParam($request->query('start'));

        if (! $start || ! $this->isOpenSlot($shop, $menu, $nominated, $start)) {
            return redirect()->route('booking.times', [$shop->slug, $menu->id, 'staff' => $nominated?->id ?? 'any', 'date' => $start?->toDateString()])
                ->withErrors(['start' => 'その時間は予約できなくなりました。別の時間をお選びください。']);
        }

        return view('booking.form', ['shop' => $shop, 'menu' => $menu, 'nominated' => $nominated, 'start' => $start]);
    }

    public function store(Request $request, string $slug, int $menu): RedirectResponse
    {
        $shop = $this->shopOf($slug);
        $menu = $this->menuOf($shop, $menu);
        $nominated = $this->nominated($request, $this->finder->candidates($shop, $menu));
        $start = $this->startParam($request->input('start'));
        abort_unless($start, 404);

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:50'],
            'customer_phone' => ['required', 'string', 'max:30', function ($attr, $value, $fail) {
                $digits = strlen(Reservation::digits($value));
                if ($digits < 10 || $digits > 11) {
                    $fail('電話番号は、市外局番からの10けたか11けたで入力してください。');
                }
            }],
            'customer_note' => ['nullable', 'string', 'max:500'],
            'agree' => ['accepted'],
        ], [
            'agree.accepted' => 'キャンセルについての説明を確認して、チェックを入れてください。',
        ], [
            'customer_name' => 'お名前', 'customer_phone' => '電話番号', 'customer_note' => 'ご要望',
        ]);

        try {
            $reservation = $this->booking->bookOnline($shop, $menu, $nominated, $start, collect($data)->except('agree')->all());
        } catch (RuntimeException $e) {
            return redirect()->route('booking.times', [$shop->slug, $menu->id, 'staff' => $nominated?->id ?? 'any', 'date' => $start->toDateString()])
                ->withErrors(['start' => $e->getMessage()]);
        }

        return redirect()->route('booking.show', $reservation->token)->with('booked', true);
    }

    public function show(string $token): View
    {
        $reservation = $this->reservationOf($token);

        return view('booking.show', ['reservation' => $reservation, 'shop' => $reservation->user]);
    }

    public function cancel(string $token): RedirectResponse
    {
        $reservation = $this->reservationOf($token);

        try {
            $this->booking->cancelByCustomer($reservation);
        } catch (RuntimeException $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }

        return redirect()->route('booking.show', $token)->with('status', '予約をキャンセルしました。');
    }

    /** スマホのカレンダーに追加するためのファイル(.ics) */
    public function ics(string $token): Response
    {
        $r = $this->reservationOf($token);
        abort_if($r->isCancelled(), 404);
        $shop = $r->user;
        $utc = fn (CarbonImmutable $d) => $d->utc()->format('Ymd\THis\Z');
        $esc = fn (?string $s) => str_replace(["\\", "\n", ',', ';'], ['\\\\', '\\n', '\\,', '\\;'], (string) $s);

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//yoyaku-note//JA', 'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:'.$r->token.'@yoyaku-note',
            'DTSTAMP:'.$utc(CarbonImmutable::now()),
            'DTSTART:'.$utc($r->starts_at),
            'DTEND:'.$utc($r->ends_at),
            'SUMMARY:'.$esc($shop->name.' '.$r->menu_name),
            'LOCATION:'.$esc($shop->address),
            'DESCRIPTION:'.$esc("予約の確認・キャンセル\n".$r->manage_url),
            'END:VEVENT', 'END:VCALENDAR', '',
        ]);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reservation.ics"',
        ]);
    }

    private function shopOf(string $slug): User
    {
        return User::where('slug', $slug)->firstOrFail();
    }

    private function menuOf(User $shop, int $id): Menu
    {
        return $shop->menus()->active()->whereKey($id)->firstOrFail();
    }

    private function reservationOf(string $token): Reservation
    {
        return Reservation::with(['user', 'staff'])->where('token', $token)->firstOrFail();
    }

    /** ?staff=担当者のid(指名)/ any(指名なし) */
    private function nominated(Request $request, $candidates): ?Staff
    {
        $id = $request->input('staff');

        return ctype_digit((string) $id) ? $candidates->firstWhere('id', (int) $id) : null;
    }

    private function isOpenSlot(User $shop, Menu $menu, ?Staff $nominated, CarbonImmutable $start): bool
    {
        return collect($this->finder->forDay($shop, $menu, $nominated, $start->startOfDay()))
            ->contains(fn ($s) => $s['start']->equalTo($start));
    }

    private function dateParam(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : null;
    }

    private function startParam(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) && strtotime($value)
            ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value) : null;
    }
}
