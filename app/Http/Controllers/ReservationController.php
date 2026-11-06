<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Staff;
use App\Models\User;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** お店用の予約表と、予約の手入力・変更 */
class ReservationController extends Controller
{
    /** 予約表の1行の長さ(分)。予約の時刻もこの単位で入れる */
    public const ROW_MINUTES = 15;

    public function __construct(private BookingService $booking) {}

    /** 1日分の予約表(縦が時刻、横が担当者) */
    public function index(Request $request): View
    {
        $user = $request->user();
        $date = $this->dateParam($request->query('date')) ?? CarbonImmutable::today();

        $reservations = $user->reservations()->with('staff')
            ->whereDate('starts_at', $date->toDateString())
            ->orderBy('starts_at')->get();
        [$active, $cancelled] = $reservations->partition(fn (Reservation $r) => ! $r->isCancelled());

        // 在籍中の担当者 + その日に予約がある担当者(休止中でも表に出す)
        $staff = $user->staff()->ordered()->get()
            ->filter(fn (Staff $s) => $s->is_active || $active->contains('staff_id', $s->id))->values();

        $dayOffs = $user->dayOffs()->whereDate('date', $date->toDateString())->get();
        $shopClosed = $dayOffs->contains(fn ($d) => $d->staff_id === null);
        $hours = $user->hoursFor($date->dayOfWeek);

        // 表の時間の範囲:営業時間(休みの日は 10〜19時)。範囲の外の予約があれば広げる
        [$from, $to] = $hours ?? ['10:00', '19:00'];
        $open = $date->setTimeFromTimeString($from);
        $close = $date->setTimeFromTimeString($to);
        foreach ($active as $r) {
            $open = $r->starts_at->lt($open) ? $r->starts_at : $open;
            $close = $r->ends_at->gt($close) ? $r->ends_at : $close;
        }
        $open = $this->floorToRow($open);

        $rows = [];
        for ($t = $open; $t->lt($close); $t = $t->addMinutes(self::ROW_MINUTES)) {
            $rows[] = $t;
        }

        // 担当者ごとに「何行目から何行ぶん」の予約か
        $cells = [];
        foreach ($active as $r) {
            if (! $r->staff_id) {
                continue;
            }
            $startRow = intdiv((int) $open->diffInMinutes($this->floorToRow($r->starts_at)), self::ROW_MINUTES);
            $span = max(1, (int) ceil($this->floorToRow($r->starts_at)->diffInMinutes($r->ends_at) / self::ROW_MINUTES));
            $cells[$r->staff_id][$startRow] = ['reservation' => $r, 'span' => $span];
        }

        $weekStart = $date->subDays($date->dayOfWeek);
        $counts = $user->reservations()->blocking()
            ->whereBetween('starts_at', [$weekStart, $weekStart->addDays(7)])
            ->get(['starts_at'])
            ->countBy(fn ($r) => $r->starts_at->toDateString());

        return view('reservations.index', [
            'date' => $date,
            'staff' => $staff,
            'rows' => $rows,
            'cells' => $cells,
            'active' => $active,
            'cancelled' => $cancelled,
            'unassigned' => $active->whereNull('staff_id'),
            'hours' => $hours,
            'shopClosed' => $shopClosed,
            'offStaff' => $dayOffs->pluck('staff_id')->filter()->all(),
            'weekStart' => $weekStart,
            'counts' => $counts,
            'total' => $active->sum('price'),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $date = $this->dateParam($request->query('date')) ?? CarbonImmutable::today();
        $time = preg_match('/^\d{2}:\d{2}$/', (string) $request->query('time')) ? $request->query('time') : ($user->hoursFor($date->dayOfWeek)[0] ?? '10:00');

        return view('reservations.form', $this->formData($user, new Reservation([
            'staff_id' => $request->integer('staff') ?: null,
            'starts_at' => $date->setTimeFromTimeString($time),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        [$menu, $staff, $start, $data] = $this->validated($request);

        try {
            $reservation = $this->booking->bookByShop($user, $menu, $staff, $start, $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['time' => $e->getMessage()]);
        }

        return redirect()->route('reservations.index', ['date' => $start->toDateString()])
            ->with('status', "{$reservation->customer_name}様の予約を入れました({$reservation->when_label} {$staff->name})。");
    }

    public function show(Reservation $reservation): View
    {
        $this->own($reservation);
        $reservation->load(['staff', 'user']);

        $history = $reservation->phone_digits === '' ? collect() : Reservation::with('staff')
            ->where('user_id', $reservation->user_id)
            ->where('phone_digits', $reservation->phone_digits)
            ->whereKeyNot($reservation->id)
            ->orderByDesc('starts_at')->limit(20)->get();

        return view('reservations.show', ['reservation' => $reservation, 'history' => $history]);
    }

    public function edit(Request $request, Reservation $reservation): View
    {
        $this->own($reservation);

        return view('reservations.form', $this->formData($request->user(), $reservation));
    }

    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->own($reservation);
        [$menu, $staff, $start, $data] = $this->validated($request, $reservation);

        try {
            $this->booking->reschedule($reservation, $menu, $staff, $start, $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['time' => $e->getMessage()]);
        }

        return redirect()->route('reservations.show', $reservation)->with('status', '予約を変更しました。');
    }

    public function status(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->own($reservation);
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(Reservation::STATUSES))]])['status'];

        try {
            $this->booking->changeStatus($reservation, $status);
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', "「".Reservation::STATUSES[$status]."」にしました。");
    }

    private function formData(User $user, Reservation $reservation): array
    {
        $times = [];
        for ($m = 6 * 60; $m < 24 * 60; $m += self::ROW_MINUTES) {
            $times[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }
        // 15分単位でない時刻の予約(営業時間が10:10からなど)も、そのまま選べるようにする
        if ($reservation->starts_at) {
            $times = collect($times)->push($reservation->starts_at->format('H:i'))->unique()->sort()->values()->all();
        }

        return [
            'reservation' => $reservation,
            'menus' => $user->menus()->ordered()->get(),
            'staff' => $user->staff()->ordered()->get()->filter(fn ($s) => $s->is_active || $s->id === $reservation->staff_id)->values(),
            'times' => $times,
        ];
    }

    /** @return array{0: \App\Models\Menu, 1: Staff, 2: CarbonImmutable, 3: array} */
    private function validated(Request $request, ?Reservation $reservation = null): array
    {
        $user = $request->user();
        $data = $request->validate([
            'menu_id' => ['required', Rule::exists('menus', 'id')->where('user_id', $user->id)],
            'staff_id' => ['required', Rule::exists('staff', 'id')->where('user_id', $user->id)],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'customer_name' => ['required', 'string', 'max:50'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_note' => ['nullable', 'string', 'max:500'],
            'shop_note' => ['nullable', 'string', 'max:1000'],
            'nominated' => ['boolean'],
        ], [
            'time.regex' => '時刻を選んでください。',
        ], [
            'menu_id' => 'メニュー', 'staff_id' => '担当者', 'date' => '日付', 'time' => '時刻',
            'customer_name' => 'お名前', 'customer_phone' => '電話番号', 'customer_note' => 'お客さんの要望', 'shop_note' => 'お店のメモ',
        ]);

        $menu = $user->menus()->findOrFail($data['menu_id']);
        $staff = $user->staff()->findOrFail($data['staff_id']);
        $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $data['date'].' '.$data['time']);

        return [$menu, $staff, $start, [
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'] ?? '',
            'customer_note' => $data['customer_note'] ?? null,
            'shop_note' => $data['shop_note'] ?? null,
            'nominated' => $request->boolean('nominated'),
        ]];
    }

    private function floorToRow(CarbonImmutable $t): CarbonImmutable
    {
        return $t->setTime($t->hour, intdiv($t->minute, self::ROW_MINUTES) * self::ROW_MINUTES);
    }

    private function dateParam(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : null;
    }
}
