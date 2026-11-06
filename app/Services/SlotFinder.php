<?php

namespace App\Services;

use App\Models\DayOff;
use App\Models\Menu;
use App\Models\Reservation;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * お客さんが予約できる時刻を探す
 *
 * 考え方:
 *  1. その日がお店の営業日か(曜日の営業時間・お店の休み)
 *  2. そのメニューができて、その日に出勤している担当者は誰か(担当者の休みも見る)
 *  3. 開店から閉店まで、設定の間隔(30分など)で開始時刻を並べ、
 *     「開始 + 所要時間」が閉店までに終わり、その担当者のほかの予約と重ならない時刻だけ残す
 *  4. 「◯時間前まで」「◯日先まで」の受付ルールの外は出さない
 */
class SlotFinder
{
    /**
     * from〜to の日ごとの空き時刻
     *
     * @return array<string, list<array{time: string, start: CarbonImmutable, staff_ids: list<int>}>>  'Y-m-d' => 時刻の一覧
     */
    public function range(User $shop, Menu $menu, ?Staff $only, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->startOfDay();
        [$first, $last] = $this->window($shop);
        if ($from->lt($first)) {
            $from = $first;
        }
        if ($to->gt($last)) {
            $to = $last;
        }
        if ($from->gt($to)) {
            return [];
        }

        $staff = $this->candidates($shop, $menu, $only);
        if ($staff->isEmpty()) {
            return [];
        }

        // 期間中のお休みと予約は、日ごとに何度も調べずに最初にまとめて読み込む
        $dayOffs = DayOff::where('user_id', $shop->id)
            // 日付は「2026-11-13 00:00:00」の形で保存されることがあるので、日付の部分だけで比べる
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get()
            ->groupBy(fn ($d) => $d->date->toDateString());
        $bookings = Reservation::blocking()
            ->whereIn('staff_id', $staff->pluck('id'))
            ->overlapping($from, $to->addDay())
            ->get(['staff_id', 'starts_at', 'ends_at'])
            ->groupBy('staff_id');

        $result = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $result[$day->toDateString()] = $this->daySlots($shop, $menu, $day, $staff, $dayOffs->get($day->toDateString(), collect()), $bookings);
        }

        return $result;
    }

    /** 1日分の空き時刻 */
    public function forDay(User $shop, Menu $menu, ?Staff $only, CarbonImmutable $day): array
    {
        return $this->range($shop, $menu, $only, $day, $day)[$day->toDateString()] ?? [];
    }

    /** 予約を受け付ける日の範囲 [今日, 今日 + ◯日] */
    public function window(User $shop): array
    {
        $today = CarbonImmutable::today();

        return [$today, $today->addDays($shop->booking_days_ahead)];
    }

    /**
     * 「指名なし」のとき、空いている担当者の中から1人選ぶ
     * その日の予約が少ない人 → 並び順が先の人
     */
    public function pickStaff(Collection $freeStaff, CarbonImmutable $day): ?Staff
    {
        $counts = Reservation::blocking()
            ->whereIn('staff_id', $freeStaff->pluck('id'))
            ->whereDate('starts_at', $day->toDateString())
            ->selectRaw('staff_id, count(*) as c')
            ->groupBy('staff_id')
            ->pluck('c', 'staff_id');

        return $freeStaff->sortBy(fn (Staff $s) => [(int) ($counts[$s->id] ?? 0), $s->sort, $s->id])->first();
    }

    /** そのメニューができる、在籍中の担当者(指名があればその人だけ) */
    public function candidates(User $shop, Menu $menu, ?Staff $only = null): Collection
    {
        return $menu->staff()
            ->where('staff.user_id', $shop->id)
            ->where('staff.is_active', true)
            ->when($only, fn ($q) => $q->where('staff.id', $only->id))
            ->orderBy('staff.sort')->orderBy('staff.id')
            ->get();
    }

    private function daySlots(User $shop, Menu $menu, CarbonImmutable $day, Collection $staff, Collection $dayOffs, Collection $bookings): array
    {
        $hours = $shop->hoursFor($day->dayOfWeek);
        if (! $hours || $dayOffs->contains(fn ($d) => $d->staff_id === null)) {
            return []; // 定休日・お店の休み
        }

        $offStaff = $dayOffs->pluck('staff_id')->filter()->all();
        $working = $staff->filter(fn (Staff $s) => $s->worksOn($day->dayOfWeek) && ! in_array($s->id, $offStaff, true));
        if ($working->isEmpty()) {
            return [];
        }

        $open = $day->setTimeFromTimeString($hours[0]);
        $close = $day->setTimeFromTimeString($hours[1]);
        $earliest = CarbonImmutable::now()->addHours($shop->min_notice_hours);
        $step = max(5, $shop->slot_interval);

        $slots = [];
        for ($start = $open; $start->addMinutes($menu->duration_minutes)->lte($close); $start = $start->addMinutes($step)) {
            if ($start->lt($earliest)) {
                continue;
            }
            $end = $start->addMinutes($menu->duration_minutes);
            $free = $working->filter(fn (Staff $s) => ! $bookings->get($s->id, collect())
                ->contains(fn ($b) => $b->starts_at->lt($end) && $b->ends_at->gt($start)));

            if ($free->isNotEmpty()) {
                $slots[] = ['time' => $start->format('H:i'), 'start' => $start, 'staff_ids' => $free->pluck('id')->values()->all()];
            }
        }

        return $slots;
    }
}
