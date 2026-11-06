<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Reservation;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** お客さんの予約ページ(ログインなし) */
class BookingTest extends TestCase
{
    use RefreshDatabase;

    private User $shop;

    private Menu $cut;

    private Staff $aoi;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00'); // 火曜の朝

        $this->shop = User::create(['name' => 'テストサロン', 'slug' => 'test-salon', 'phone' => '03-1111-2222', 'email' => 'shop@example.com', 'password' => 'password']);
        $this->cut = $this->shop->menus()->create(['name' => 'カット', 'duration_minutes' => 60, 'price' => 4400]);
        $this->aoi = $this->shop->staff()->create(['name' => 'あおい', 'work_weekdays' => range(0, 6)]);
        $this->aoi->menus()->attach($this->cut->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function bookingData(array $override = []): array
    {
        return $override + [
            'start' => '2026-11-11T10:00', 'staff' => 'any',
            'customer_name' => '山田 花子', 'customer_phone' => '090-1234-5678', 'customer_note' => '短めに', 'agree' => '1',
        ];
    }

    public function test_customer_can_book_from_menu_to_confirmation(): void
    {
        $this->get('/r/test-salon')->assertOk()->assertSee('テストサロン')->assertSee('カット')->assertSee('¥4,400');
        $this->get("/r/test-salon/menus/{$this->cut->id}?date=2026-11-11")->assertOk()->assertSee('11月11日')->assertSee('10:00');
        $this->get("/r/test-salon/menus/{$this->cut->id}/form?staff=any&start=2026-11-11T10:00")->assertOk()->assertSee('11/11(水) 10:00〜11:00');

        $response = $this->post("/r/test-salon/menus/{$this->cut->id}", $this->bookingData());

        $r = Reservation::sole();
        $response->assertRedirect("/b/{$r->token}");
        $this->assertSame('web', $r->source);
        $this->assertSame($this->aoi->id, $r->staff_id);
        $this->assertSame('09012345678', $r->phone_digits);
        $this->assertSame('2026-11-11 11:00', $r->ends_at->format('Y-m-d H:i'));
        $this->assertFalse($r->nominated);

        $this->get("/b/{$r->token}")->assertOk()->assertSee('ご予約ありがとうございます')->assertSee('山田 花子 様')->assertSee('予約をキャンセルする');
    }

    public function test_same_slot_cannot_be_booked_twice(): void
    {
        $this->post("/r/test-salon/menus/{$this->cut->id}", $this->bookingData())->assertRedirect();
        $this->post("/r/test-salon/menus/{$this->cut->id}", $this->bookingData(['customer_name' => '別の人']))
            ->assertRedirect("/r/test-salon/menus/{$this->cut->id}?staff=any&date=2026-11-11")
            ->assertSessionHasErrors('start');

        $this->assertSame(1, Reservation::count());
    }

    public function test_slot_that_would_overlap_or_run_past_closing_is_rejected(): void
    {
        $this->post("/r/test-salon/menus/{$this->cut->id}", $this->bookingData(['start' => '2026-11-11T18:30']))->assertSessionHasErrors('start');
        $this->post("/r/test-salon/menus/{$this->cut->id}", $this->bookingData(['start' => '2026-11-16T10:00']))->assertSessionHasErrors('start'); // 定休日
        $this->assertSame(0, Reservation::count());
    }

    public function test_no_preference_picks_the_staff_member_with_fewer_bookings(): void
    {
        $ren = $this->shop->staff()->create(['name' => 'れん', 'work_weekdays' => range(0, 6)]);
        $ren->menus()->attach($this->cut->id);
        // あおいさん(並び順が先)にはもう1件ある
        $this->post("/r/test-salon/menus/{$this->cut->id}", $this->bookingData(['start' => '2026-11-11T15:00', 'staff' => $this->aoi->id]));

        $this->post("/r/test-salon/menus/{$this->cut->id}", $this->bookingData(['customer_name' => '指名なしの人']));
        $this->assertSame($ren->id, Reservation::where('customer_name', '指名なしの人')->value('staff_id'));
        $this->assertTrue((bool) Reservation::where('staff_id', $this->aoi->id)->value('nominated'));
    }

    public function test_phone_and_agreement_are_required(): void
    {
        $this->post("/r/test-salon/menus/{$this->cut->id}", $this->bookingData(['customer_phone' => '1234', 'agree' => null]))
            ->assertSessionHasErrors(['customer_phone', 'agree']);
        $this->assertSame(0, Reservation::count());

        // 全角やハイフンなしでも受け付ける
        $this->post("/r/test-salon/menus/{$this->cut->id}", $this->bookingData(['customer_phone' => '０９０１２３４５６７８']))->assertRedirect();
        $this->assertSame('09012345678', Reservation::sole()->phone_digits);
    }

    public function test_customer_can_cancel_only_before_the_deadline(): void
    {
        $this->post("/r/test-salon/menus/{$this->cut->id}", $this->bookingData(['start' => '2026-11-12T10:00']));
        $r = Reservation::sole();

        // 24時間前より後(11/11 10:01)はキャンセルできない
        Carbon::setTestNow('2026-11-11 10:01:00');
        $this->get("/b/{$r->token}")->assertSee('お店に直接ご連絡ください')->assertDontSee('予約をキャンセルする');
        $this->post("/b/{$r->token}/cancel")->assertSessionHasErrors('cancel');
        $this->assertSame('confirmed', $r->fresh()->status);

        // 締め切り前ならキャンセルでき、その時間はまた予約できる
        Carbon::setTestNow('2026-11-11 09:00:00');
        $this->post("/b/{$r->token}/cancel")->assertRedirect("/b/{$r->token}");
        $this->assertSame('cancelled', $r->fresh()->status);
        $this->assertNotNull($r->fresh()->cancelled_at);
        $this->get("/r/test-salon/menus/{$this->cut->id}?date=2026-11-12")->assertSee('/form?staff=any&amp;start=2026-11-12T10%3A00', false);
    }

    public function test_calendar_file_and_unknown_pages(): void
    {
        $this->post("/r/test-salon/menus/{$this->cut->id}", $this->bookingData());
        $r = Reservation::sole();

        $this->get("/b/{$r->token}/calendar.ics")->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=UTF-8')
            ->assertSee('DTSTART:20261111T010000Z'); // 日本時間10時 = UTC 1時

        $this->get('/b/not-a-real-token')->assertNotFound();
        $this->get('/r/no-such-shop')->assertNotFound();
        $other = User::create(['name' => '別の店', 'email' => 'other@example.com', 'password' => 'password']);
        $otherMenu = $other->menus()->create(['name' => '他店のメニュー', 'duration_minutes' => 30, 'price' => 1000]);
        $this->get("/r/test-salon/menus/{$otherMenu->id}")->assertNotFound(); // 他のお店のメニューは開けない
    }

    public function test_inactive_menu_is_hidden_and_cannot_be_booked(): void
    {
        $this->cut->update(['is_active' => false]);

        $this->get('/r/test-salon')->assertOk()->assertDontSee('カット')->assertSee('ただいまネット予約は受け付けていません');
        $this->get("/r/test-salon/menus/{$this->cut->id}")->assertNotFound();
    }
}
