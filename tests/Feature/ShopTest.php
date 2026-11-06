<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Reservation;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** お店側の画面(予約表・手入力・メニュー・担当者・お休み・お客さん・設定) */
class ShopTest extends TestCase
{
    use RefreshDatabase;

    private User $shop;

    private Menu $cut;

    private Staff $aoi;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00');

        $this->shop = User::create(['name' => 'テストサロン', 'slug' => 'test-salon', 'email' => 'shop@example.com', 'password' => 'password']);
        $this->cut = $this->shop->menus()->create(['name' => 'カット', 'duration_minutes' => 60, 'price' => 4400]);
        $this->aoi = $this->shop->staff()->create(['name' => 'あおい', 'work_weekdays' => range(0, 6)]);
        $this->aoi->menus()->attach($this->cut->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function manual(array $override = []): array
    {
        return $override + [
            'menu_id' => $this->cut->id, 'staff_id' => $this->aoi->id, 'date' => '2026-11-11', 'time' => '13:00',
            'customer_name' => '電話の人', 'customer_phone' => '080-1111-2222', 'nominated' => '1',
        ];
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/reservations')->assertRedirect('/login');
    }

    public function test_shop_can_add_a_phone_reservation_but_not_a_double_booking(): void
    {
        $this->actingAs($this->shop)->post('/reservations', $this->manual())->assertRedirect('/reservations?date=2026-11-11');
        $r = Reservation::sole();
        $this->assertSame('shop', $r->source);
        $this->assertSame('2026-11-11 14:00', $r->ends_at->format('Y-m-d H:i'));

        // 13:30 からは重なるので入れられない
        $this->actingAs($this->shop)->from('/reservations/create')->post('/reservations', $this->manual(['time' => '13:30']))
            ->assertRedirect('/reservations/create')->assertSessionHasErrors('time');
        $this->assertSame(1, Reservation::count());

        // お店からの入力は、営業時間外や直前でも入れられる(電話で受けたので)
        $this->actingAs($this->shop)->post('/reservations', $this->manual(['date' => '2026-11-10', 'time' => '09:15']))->assertRedirect();
        $this->assertSame(2, Reservation::count());

        // ネット予約では、その時間はもう出ない
        $this->get("/r/test-salon/menus/{$this->cut->id}?date=2026-11-11")->assertDontSee('start=2026-11-11T13%3A00', false);
    }

    public function test_board_and_detail_pages_show_the_reservation(): void
    {
        $this->actingAs($this->shop)->post('/reservations', $this->manual());
        $r = Reservation::sole();

        $this->actingAs($this->shop)->get('/reservations?date=2026-11-11')->assertOk()->assertSee('電話の人様')->assertSee('あおい');
        $this->actingAs($this->shop)->get("/reservations/{$r->id}")->assertOk()->assertSee('はじめてのご予約です')->assertSee($r->manage_url);
        $this->actingAs($this->shop)->get("/reservations/{$r->id}/edit")->assertOk();
        $this->actingAs($this->shop)->get('/reservations/create?date=2026-11-11&staff='.$this->aoi->id.'&time=15:00')->assertOk();
    }

    public function test_reschedule_checks_overlaps_but_ignores_itself(): void
    {
        $this->actingAs($this->shop)->post('/reservations', $this->manual());
        $this->actingAs($this->shop)->post('/reservations', $this->manual(['time' => '15:00', 'customer_name' => '2人目']));
        $first = Reservation::where('customer_name', '電話の人')->sole();

        // 30分ずらす(自分とは重なってOK)
        $this->actingAs($this->shop)->put("/reservations/{$first->id}", $this->manual(['time' => '13:30']))->assertRedirect("/reservations/{$first->id}");
        $this->assertSame('13:30', $first->fresh()->starts_at->format('H:i'));

        // 2人目と重なる時間には動かせない
        $this->actingAs($this->shop)->put("/reservations/{$first->id}", $this->manual(['time' => '14:30']))->assertSessionHasErrors('time');
        $this->assertSame('13:30', $first->fresh()->starts_at->format('H:i'));
    }

    public function test_menu_change_on_reschedule_copies_the_new_menu(): void
    {
        $color = $this->shop->menus()->create(['name' => 'カラー', 'duration_minutes' => 90, 'price' => 6600]);
        $this->actingAs($this->shop)->post('/reservations', $this->manual());
        $r = Reservation::sole();

        // メニューの料金をあとから変えても、予約の料金は変わらない
        $this->cut->update(['price' => 5000]);
        $this->actingAs($this->shop)->put("/reservations/{$r->id}", $this->manual());
        $this->assertSame(4400, $r->fresh()->price);

        $this->actingAs($this->shop)->put("/reservations/{$r->id}", $this->manual(['menu_id' => $color->id]));
        $r->refresh();
        $this->assertSame(['カラー', 6600, '14:30'], [$r->menu_name, $r->price, $r->ends_at->format('H:i')]);
    }

    public function test_status_changes_and_reopening_a_cancelled_slot_checks_for_overlap(): void
    {
        $this->actingAs($this->shop)->post('/reservations', $this->manual());
        $r = Reservation::sole();

        $this->actingAs($this->shop)->post("/reservations/{$r->id}/status", ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame('cancelled', $r->fresh()->status);

        // 空いた時間に別の予約が入ったら、キャンセルを「予約」に戻せない
        $this->actingAs($this->shop)->post('/reservations', $this->manual(['customer_name' => '代わりの人']));
        $this->actingAs($this->shop)->post("/reservations/{$r->id}/status", ['status' => 'confirmed'])->assertSessionHasErrors('status');
        $this->assertSame('cancelled', $r->fresh()->status);

        $this->actingAs($this->shop)->post("/reservations/{$r->id}/status", ['status' => 'bogus'])->assertSessionHasErrors('status');
    }

    public function test_other_shops_data_is_not_visible(): void
    {
        $this->actingAs($this->shop)->post('/reservations', $this->manual());
        $r = Reservation::sole();
        $other = User::create(['name' => '別の店', 'email' => 'other@example.com', 'password' => 'password']);

        $this->actingAs($other)->get("/reservations/{$r->id}")->assertNotFound();
        $this->actingAs($other)->post("/reservations/{$r->id}/status", ['status' => 'cancelled'])->assertNotFound();
        $this->actingAs($other)->get("/menus/{$this->cut->id}/edit")->assertNotFound();
        $this->actingAs($other)->get("/staff/{$this->aoi->id}/edit")->assertNotFound();
        $this->actingAs($other)->get('/customers/08011112222')->assertNotFound();
        // 他のお店のメニュー・担当者では予約を入れられない
        $this->actingAs($other)->post('/reservations', $this->manual())->assertSessionHasErrors(['menu_id', 'staff_id']);
        $this->assertSame('confirmed', $r->fresh()->status);
    }

    public function test_menu_and_staff_forms_link_only_own_records(): void
    {
        $other = User::create(['name' => '別の店', 'email' => 'other@example.com', 'password' => 'password']);
        $stranger = $other->staff()->create(['name' => 'よその人', 'work_weekdays' => [1]]);

        $this->actingAs($this->shop)->post('/menus', [
            'name' => 'ヘッドスパ', 'duration_minutes' => 30, 'price' => '3,300', 'is_active' => '1',
            'staff_ids' => [$this->aoi->id, $stranger->id],
        ])->assertRedirect('/menus');
        $spa = Menu::where('name', 'ヘッドスパ')->sole();
        $this->assertSame(3300, $spa->price);
        $this->assertSame([$this->aoi->id], $spa->staff()->pluck('staff.id')->all());

        $this->actingAs($this->shop)->post('/staff', [
            'name' => 'れん', 'work_weekdays' => [2, 3], 'menu_ids' => [$spa->id], 'is_active' => '1',
        ])->assertRedirect('/staff');
        $ren = Staff::where('name', 'れん')->sole();
        $this->assertSame([2, 3], $ren->work_weekdays);
        $this->assertSame([$spa->id], $ren->menus()->pluck('menus.id')->all());

        $this->actingAs($this->shop)->get('/menus')->assertOk()->assertSee('ヘッドスパ');
        $this->actingAs($this->shop)->get('/staff')->assertOk()->assertSee('火・水');
    }

    public function test_staff_with_upcoming_reservations_cannot_be_deleted(): void
    {
        $this->actingAs($this->shop)->post('/reservations', $this->manual());

        $this->actingAs($this->shop)->delete("/staff/{$this->aoi->id}")->assertSessionHasErrors('delete');
        $this->assertNotNull($this->aoi->fresh());
    }

    public function test_day_off_blocks_online_booking_and_warns_about_existing_reservations(): void
    {
        $this->actingAs($this->shop)->post('/reservations', $this->manual());

        $this->actingAs($this->shop)->post('/day-offs', ['date' => '2026-11-11', 'staff_id' => ''])
            ->assertRedirect('/day-offs')->assertSessionHas('warning');
        $this->actingAs($this->shop)->post('/day-offs', ['date' => '2026-11-11', 'staff_id' => '']); // 2回押しても1件
        $this->assertSame(1, $this->shop->dayOffs()->count());

        $this->get("/r/test-salon/menus/{$this->cut->id}?date=2026-11-11")->assertSee('この日は予約できる時間がありません');
        $this->actingAs($this->shop)->get('/day-offs')->assertOk()->assertSee('お店ごと休み');
    }

    public function test_customers_are_grouped_by_phone_number(): void
    {
        $this->actingAs($this->shop)->post('/reservations', $this->manual());
        $this->actingAs($this->shop)->post('/reservations', $this->manual(['date' => '2026-11-12', 'customer_phone' => '08011112222', 'customer_name' => '電話の人(新しい名前)']));
        $first = Reservation::orderBy('id')->first();
        $this->actingAs($this->shop)->post("/reservations/{$first->id}/status", ['status' => 'visited']);

        $this->actingAs($this->shop)->get('/customers')->assertOk()->assertSee('電話の人(新しい名前)様')->assertSee('1回');
        $this->actingAs($this->shop)->get('/customers?q=1111')->assertOk()->assertSee('電話の人(新しい名前)様');
        $this->actingAs($this->shop)->get('/customers?q=いない人')->assertOk()->assertSee('見つかりませんでした');
        $this->actingAs($this->shop)->get('/customers/08011112222')->assertOk()->assertSee('¥4,400')->assertSee('あおい')
            // 上に「これからの予約」(11/12)、下に月ごとの履歴(来店済みの11/11)
            ->assertSeeInOrder(['これからの予約', '11/12(木)', 'これまでの履歴', '2026年11月', '11/11(水)']);
    }

    public function test_settings_update_hours_and_validate_them(): void
    {
        $hours = [];
        foreach (range(0, 6) as $w) {
            $hours[$w] = ['open' => $w === 3 ? '0' : '1', 'from' => '09:00', 'to' => '18:00'];
        }
        $payload = [
            'name' => '新しい名前', 'slug' => 'New-Salon', 'phone' => '03-0000-0000', 'hours' => $hours,
            'slot_interval' => 60, 'booking_days_ahead' => 14, 'min_notice_hours' => 0, 'cancel_deadline_hours' => 48,
            'email' => 'shop@example.com',
        ];

        $this->actingAs($this->shop)->put('/settings', $payload)->assertRedirect('/settings')->assertSessionHasNoErrors();
        $this->shop->refresh();
        $this->assertSame('new-salon', $this->shop->slug);
        $this->assertNull($this->shop->hoursFor(3));
        $this->assertSame(['09:00', '18:00'], $this->shop->hoursFor(1));
        $this->assertSame(60, $this->shop->slot_interval);

        $payload['hours'][1] = ['open' => '1', 'from' => '18:00', 'to' => '10:00'];
        $this->actingAs($this->shop)->put('/settings', $payload)->assertSessionHasErrors('hours.1.to');

        $payload['hours'][1] = ['open' => '1', 'from' => '09:00', 'to' => '18:00'];
        $payload['slug'] = 'あいう';
        $this->actingAs($this->shop)->put('/settings', $payload)->assertSessionHasErrors('slug');
    }

    public function test_dashboard_lists_new_web_bookings_by_date_with_new_badge(): void
    {
        // 先に入った「11/20」、あとから入った「11/12」。並びは予約の日付が近い順
        foreach ([['2026-11-20T10:00', '遠い日の人'], ['2026-11-12T10:00', '近い日の人']] as [$start, $name]) {
            $this->post("/r/test-salon/menus/{$this->cut->id}", [
                'start' => $start, 'staff' => 'any', 'customer_name' => $name, 'customer_phone' => '090-1234-5678', 'agree' => '1',
            ])->assertRedirect();
        }
        // 2日前に受け付けたことにする(新着マークは付かない)
        Reservation::where('customer_name', '遠い日の人')->update(['created_at' => now()->subDays(2)]);

        $this->actingAs($this->shop)->get('/')->assertOk()
            ->assertSeeInOrder(['11/12(木)', '近い日の人', '新着', '11/20(金)', '遠い日の人']);
        $html = $this->actingAs($this->shop)->get('/')->getContent();
        $this->assertSame(1, substr_count($html, '>新着<'));
    }

    public function test_all_screens_render_with_demo_data(): void
    {
        $this->seed();
        $demo = User::where('email', 'demo@example.com')->sole();
        $r = $demo->reservations()->first();
        $menu = $demo->menus()->first();
        $phone = $demo->reservations()->where('phone_digits', '!=', '')->value('phone_digits');

        foreach (['/', '/reservations', '/reservations?date=2026-11-03', '/reservations/create', "/reservations/{$r->id}", "/reservations/{$r->id}/edit",
            '/menus', '/menus/create', "/menus/{$menu->id}/edit", '/staff', '/staff/create', '/day-offs', '/customers', "/customers/{$phone}", '/settings'] as $url) {
            $this->actingAs($demo)->get($url)->assertOk();
        }
        foreach (['/r/demo', "/r/demo/menus/{$menu->id}", "/b/{$r->token}"] as $url) {
            $this->get($url)->assertOk();
        }
        $this->assertGreaterThan(30, $demo->reservations()->count());
    }
}
