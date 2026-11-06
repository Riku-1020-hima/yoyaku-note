<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * お客さんの一覧(電話番号が同じ予約を、同じお客さんとしてまとめる)
 * 会員登録はないので、電話番号を「お客さんの番号」として使う
 */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $q = trim((string) $request->query('q'));
        $digits = Reservation::digits($q);
        $now = now()->format('Y-m-d H:i:s');

        $customers = Reservation::query()
            ->where('user_id', $user->id)
            ->where('phone_digits', '!=', '')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('customer_name', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->when(strlen($digits) >= 3, fn ($w2) => $w2->orWhere('phone_digits', 'like', '%'.$digits.'%'))))
            ->groupBy('phone_digits')
            ->select('phone_digits')
            ->selectRaw('max(id) as latest_id')
            ->selectRaw("sum(case when status = 'visited' then 1 else 0 end) as visits")
            ->selectRaw("sum(case when status = 'no_show' then 1 else 0 end) as no_shows")
            ->selectRaw("max(case when status = 'visited' then starts_at end) as last_visit")
            ->selectRaw("min(case when status = 'confirmed' and starts_at >= ? then starts_at end) as next_at", [$now])
            ->selectRaw('max(starts_at) as last_at')
            ->orderByDesc('last_at')
            ->paginate(30)
            ->withQueryString();

        // 名前と電話番号は、いちばん新しい予約のものを出す
        $latest = Reservation::whereIn('id', $customers->pluck('latest_id'))->get(['id', 'customer_name', 'customer_phone'])->keyBy('id');

        return view('customers.index', ['customers' => $customers, 'latest' => $latest, 'q' => $q]);
    }

    public function show(Request $request, string $phone): View
    {
        $reservations = Reservation::with('staff')
            ->where('user_id', $request->user()->id)
            ->where('phone_digits', $phone)
            ->orderByDesc('starts_at')
            ->get();
        abort_if($reservations->isEmpty(), 404);

        $visited = $reservations->where('status', 'visited');
        // これからの予約(近い順)と、それ以外の履歴(月ごと・新しい順)に分ける
        [$upcoming, $past] = $reservations->partition(fn ($r) => $r->status === 'confirmed' && $r->starts_at->isFuture());

        return view('customers.show', [
            'upcoming' => $upcoming->sortBy('starts_at')->values(),
            'pastByMonth' => $past->sortByDesc('starts_at')->groupBy(fn ($r) => $r->starts_at->format('Y年n月')),
            'latest' => $reservations->sortByDesc('id')->first(),
            'stats' => [
                'visits' => $visited->count(),
                'spent' => $visited->sum('price'),
                'no_shows' => $reservations->where('status', 'no_show')->count(),
                'cancels' => $reservations->where('status', 'cancelled')->count(),
                'last_visit' => $visited->max('starts_at'),
                'next' => $reservations->where('status', 'confirmed')->filter(fn ($r) => $r->starts_at->isFuture())->sortBy('starts_at')->first(),
                'favorite_staff' => $visited->whereNotNull('staff_id')->countBy(fn ($r) => $r->staff?->name)->sortDesc()->keys()->first(),
            ],
        ]);
    }
}
