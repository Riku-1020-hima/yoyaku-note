<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Reservation;
use App\Models\Staff;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 空き時間の計算(app/Services/SlotFinder.php) */
class SlotFinderTest extends TestCase
{
    use RefreshDatabase;

    private User $shop;

    private Menu $cut;

    private Staff $aoi;

    protected function setUp(): void
    {
        parent::setUp();
        // 2026/11/10(火)の朝9時ということにする
        \Illuminate\Support\Carbon::setTestNow('2026-11-10 09:00:00');

        // 月曜定休・10〜19時・30分ごと・2時間前まで・30日先まで
        $this->shop = User::create(['name' => 'テストサロン', 'email' => 'shop@example.com', 'password' => 'password']);
        $this->cut = $this->shop->menus()->create(['name' => 'カット', 'duration_minutes' => 60, 'price' => 4400]);
        $this->aoi = $this->staffMember('あおい');
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Carbon::setTestNow();
        parent::tearDown();
    }

    private function staffMember(string $name, array $days = [0, 1, 2, 3, 4, 5, 6], ?Menu $menu = null): Staff
    {
        $s = $this->shop->staff()->create(['name' => $name, 'work_weekdays' => $days]);
        $s->menus()->attach(($menu ?? $this->cut)->id);

        return $s;
    }

    private function book(Staff $staff, string $from, string $to, string $status = 'confirmed'): Reservation
    {
        $r = new Reservation([
            'staff_id' => $staff->id, 'menu_id' => $this->cut->id, 'menu_name' => 'カット', 'price' => 4400, 'duration_minutes' => 60,
            'starts_at' => CarbonImmutable::parse($from), 'ends_at' => CarbonImmutable::parse($to),
            'customer_name' => '客', 'customer_phone' => '090-0000-0000', 'status' => $status,
        ]);
        $r->user()->associate($this->shop);
        $r->save();

        return $r;
    }

    private function times(string $date, ?Staff $only = null, ?Menu $menu = null): array
    {
        return array_column(app(SlotFinder::class)->forDay($this->shop, $menu ?? $this->cut, $only, CarbonImmutable::createFromFormat('!Y-m-d', $date)), 'time');
    }

    public function test_slots_run_from_opening_until_the_menu_still_ends_by_closing(): void
    {
        $times = $this->times('2026-11-11'); // 水曜

        $this->assertSame('10:00', $times[0]);
        $this->assertSame('18:00', end($times)); // 60分なので、19時閉店なら18時が最後
        $this->assertCount(17, $times);
    }

    public function test_closed_weekday_and_shop_day_off_have_no_slots(): void
    {
        $this->assertSame([], $this->times('2026-11-16')); // 月曜(定休日)

        $this->shop->dayOffs()->create(['date' => '2026-11-12']);
        $this->assertSame([], $this->times('2026-11-12'));
    }

    public function test_existing_reservation_blocks_overlapping_start_times(): void
    {
        $this->book($this->aoi, '2026-11-11 11:00', '2026-11-11 12:00');

        $times = $this->times('2026-11-11');
        $this->assertContains('10:00', $times);   // 10:00〜11:00 はちょうど空いている
        $this->assertNotContains('10:30', $times); // 10:30〜11:30 は重なる
        $this->assertNotContains('11:00', $times);
        $this->assertNotContains('11:30', $times);
        $this->assertContains('12:00', $times);
    }

    public function test_cancelled_reservation_does_not_block(): void
    {
        $this->book($this->aoi, '2026-11-11 11:00', '2026-11-11 12:00', 'cancelled');

        $this->assertContains('11:00', $this->times('2026-11-11'));
    }

    public function test_slot_stays_open_while_another_staff_member_is_free(): void
    {
        $ren = $this->staffMember('れん');
        $this->book($this->aoi, '2026-11-11 11:00', '2026-11-11 12:00');

        $slot = collect(app(SlotFinder::class)->forDay($this->shop, $this->cut, null, CarbonImmutable::createFromFormat('!Y-m-d', '2026-11-11')))->firstWhere('time', '11:00');
        $this->assertSame([$ren->id], $slot['staff_ids']);

        // あおいさんを指名すると、11時は選べない
        $this->assertNotContains('11:00', $this->times('2026-11-11', $this->aoi));
    }

    public function test_staff_who_cannot_do_the_menu_or_is_off_is_not_a_candidate(): void
    {
        $perm = $this->shop->menus()->create(['name' => 'パーマ', 'duration_minutes' => 120, 'price' => 9000]);
        $this->assertSame([], $this->times('2026-11-11', null, $perm)); // だれもパーマができない

        $this->staffMember('みく', [4], $perm); // 木曜だけ出勤
        $this->assertSame([], $this->times('2026-11-11', null, $perm)); // 水曜はいない
        $this->assertContains('10:00', $this->times('2026-11-12', null, $perm));

        // あおいさんの休みの日は、あおいさんの枠がなくなる
        $this->shop->dayOffs()->create(['date' => '2026-11-13', 'staff_id' => $this->aoi->id]);
        $this->assertSame([], $this->times('2026-11-13'));
    }

    public function test_minimum_notice_and_booking_window(): void
    {
        // 今は9時、2時間前までなので、今日は11時から
        $this->assertSame('11:00', $this->times('2026-11-10')[0]);

        // 30日先までなので、31日後は出ない
        $this->assertNotEmpty($this->times('2026-12-09')); // 29日後(水)
        $this->assertSame([], $this->times('2026-12-11'));  // 31日後(金)
        $this->assertSame([], $this->times('2026-11-09'));  // 過去
    }

    public function test_range_returns_every_day_in_the_window(): void
    {
        $days = app(SlotFinder::class)->range($this->shop, $this->cut, null, CarbonImmutable::createFromFormat('!Y-m-d', '2026-11-10'), CarbonImmutable::createFromFormat('!Y-m-d', '2026-11-16'));

        $this->assertCount(7, $days);
        $this->assertSame([], $days['2026-11-16']); // 月曜
        $this->assertNotEmpty($days['2026-11-14']);
    }
}
