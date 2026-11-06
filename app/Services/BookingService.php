<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\Reservation;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 予約を入れる・変える・取り消す
 *
 * 同じ時間に2人が同時に予約しても二重にならないよう、
 * お店の担当者の行をロックしてから(順番待ちにしてから)空きを確かめ直して保存する
 */
class BookingService
{
    public function __construct(private SlotFinder $finder) {}

    /**
     * お客さんが予約ページから予約する
     *
     * @param  array{customer_name: string, customer_phone: string, customer_note?: ?string}  $customer
     */
    public function bookOnline(User $shop, Menu $menu, ?Staff $nominated, CarbonImmutable $start, array $customer): Reservation
    {
        return DB::transaction(function () use ($shop, $menu, $nominated, $start, $customer) {
            $this->lockShop($shop);

            $slot = collect($this->finder->forDay($shop, $menu, $nominated, $start->startOfDay()))
                ->first(fn ($s) => $s['start']->equalTo($start));
            if (! $slot) {
                throw new RuntimeException('申し訳ありません。その時間はちょうど埋まってしまいました。別の時間をお選びください。');
            }

            $free = Staff::whereKey($slot['staff_ids'])->get();
            $staff = $nominated ?? $this->finder->pickStaff($free, $start);

            return $this->create($shop, $menu, $staff, $start, $customer + [
                'source' => 'web',
                'nominated' => $nominated !== null,
            ]);
        });
    }

    /**
     * お店が予約を入れる(電話・来店で受けた予約)
     * 受付ルール(◯時間前まで など)は見ないが、同じ担当者の予約との重なりだけは許さない
     */
    public function bookByShop(User $shop, Menu $menu, Staff $staff, CarbonImmutable $start, array $data): Reservation
    {
        return DB::transaction(function () use ($shop, $menu, $staff, $start, $data) {
            $this->lockShop($shop);
            $this->assertFree($staff, $start, $start->addMinutes($menu->duration_minutes));

            return $this->create($shop, $menu, $staff, $start, $data + ['source' => 'shop']);
        });
    }

    /** お店が予約の内容(日時・担当者・メニュー)を変える */
    public function reschedule(Reservation $reservation, Menu $menu, Staff $staff, CarbonImmutable $start, array $data): Reservation
    {
        return DB::transaction(function () use ($reservation, $menu, $staff, $start, $data) {
            $this->lockShop($reservation->user);

            $menuChanged = $reservation->menu_id !== $menu->id;
            // メニューを変えたときだけ、今のメニューの名前・料金・時間を写し直す
            $duration = $menuChanged ? $menu->duration_minutes : $reservation->duration_minutes;
            $end = $start->addMinutes($duration);
            if (in_array($reservation->status, Reservation::BLOCKING, true)) {
                $this->assertFree($staff, $start, $end, $reservation->id);
            }

            $reservation->fill($data);
            $reservation->staff_id = $staff->id;
            $reservation->starts_at = $start;
            $reservation->ends_at = $end;
            if ($menuChanged) {
                $reservation->fill(['menu_id' => $menu->id, 'menu_name' => $menu->name, 'price' => $menu->price, 'duration_minutes' => $menu->duration_minutes]);
            }
            $reservation->save();

            return $reservation;
        });
    }

    /** 予約の状態を変える(来店済み・無断キャンセル・キャンセル・予約に戻す) */
    public function changeStatus(Reservation $reservation, string $status): void
    {
        DB::transaction(function () use ($reservation, $status) {
            $this->lockShop($reservation->user);

            // キャンセルを「予約」に戻すときは、その間に別の予約が入っていないか確かめる
            if ($reservation->isCancelled() && in_array($status, Reservation::BLOCKING, true) && $reservation->staff) {
                $this->assertFree($reservation->staff, $reservation->starts_at, $reservation->ends_at, $reservation->id);
            }

            $reservation->status = $status;
            $reservation->cancelled_at = $status === 'cancelled' ? ($reservation->cancelled_at ?? now()) : null;
            $reservation->save();
        });
    }

    /** お客さんが確認ページからキャンセルする */
    public function cancelByCustomer(Reservation $reservation): void
    {
        if (! $reservation->canCancelOnline($reservation->user)) {
            throw new RuntimeException('この予約は、こちらのページからはキャンセルできません。お店にご連絡ください。');
        }
        $this->changeStatus($reservation, 'cancelled');
    }

    private function create(User $shop, Menu $menu, Staff $staff, CarbonImmutable $start, array $data): Reservation
    {
        $reservation = new Reservation($data + [
            'staff_id' => $staff->id,
            'menu_id' => $menu->id,
            'menu_name' => $menu->name,
            'price' => $menu->price,
            'duration_minutes' => $menu->duration_minutes,
            'starts_at' => $start,
            'ends_at' => $start->addMinutes($menu->duration_minutes),
            'status' => 'confirmed',
        ]);
        $reservation->user()->associate($shop);
        $reservation->save();

        return $reservation;
    }

    private function assertFree(Staff $staff, CarbonImmutable $start, CarbonImmutable $end, ?int $ignoreId = null): void
    {
        $clash = Reservation::blocking()
            ->where('staff_id', $staff->id)
            ->overlapping($start, $end)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->orderBy('starts_at')
            ->first();

        if ($clash) {
            throw new RuntimeException("{$staff->name}さんは、その時間に別の予約({$clash->customer_name}様 {$clash->when_label})が入っています。");
        }
    }

    /** このお店の予約の書き込みを1つずつ順番に行う */
    private function lockShop(User $shop): void
    {
        User::whereKey($shop->id)->lockForUpdate()->first();
    }
}
