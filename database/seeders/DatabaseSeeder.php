<?php

namespace Database\Seeders;

use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * デモ用データ(架空の美容室)。ログイン: demo@example.com / password
 *  担当者3人・メニュー6つ・3週間前から2週間先までの予約(毎回「今日」を基準に作る)
 *  お客さんの予約ページ: /r/demo
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(20261104); // 毎回同じ並びのデモデータにする

        $shop = User::create([
            'name' => 'ヘアサロン ことり',
            'slug' => config('demo.shop_slug', 'demo'),
            'phone' => '03-0000-0000',
            'address' => '東京都渋谷区ことり町1-2-3 ことりビル2階',
            'notice' => "はじめての方は、カウンセリングのため10分ほど早めにお越しください。\nお支払いは現金・カード・QRコード決済が使えます。",
            'email' => 'demo@example.com',
            'password' => 'password',
        ]); // 営業時間は、はじめの設定(月曜定休・10〜19時)

        $menus = collect([
            ['カット', 60, 4400, 'シャンプー・ブロー込みです。'],
            ['カット+カラー', 120, 9900, '白髪染め・おしゃれ染めどちらもできます。'],
            ['カラー(根元のリタッチ)', 90, 6600, '伸びてきた根元だけを染めます。'],
            ['カット+パーマ', 150, 12100, 'デジタルパーマは+2,200円です(当日ご相談ください)。'],
            ['ヘッドスパ', 30, 3300, '頭皮をすっきりさせるマッサージです。ほかのメニューと一緒の方は、ご要望にお書きください。'],
            ['前髪カット', 30, 1100, null],
        ])->map(fn ($m, $i) => $shop->menus()->create([
            'name' => $m[0], 'duration_minutes' => $m[1], 'price' => $m[2], 'description' => $m[3], 'sort' => $i + 1,
        ]));

        // [名前, 肩書, 出勤する曜日(0=日), できないメニューの番号]
        $staff = collect([
            ['佐藤 あおい', '店長', [0, 2, 3, 4, 5, 6], []],
            ['鈴木 れん', 'スタイリスト', [0, 2, 4, 5, 6], []],
            ['高橋 みく', 'ジュニアスタイリスト', [0, 3, 4, 5, 6], [3]], // パーマはまだ担当しない
        ])->map(function ($s, $i) use ($shop, $menus) {
            $member = $shop->staff()->create(['name' => $s[0], 'title' => $s[1], 'work_weekdays' => $s[2], 'sort' => $i + 1]);
            $member->menus()->sync($menus->reject(fn ($m, $k) => in_array($k, $s[3], true))->pluck('id'));

            return $member;
        });

        $today = CarbonImmutable::today();
        // お休み:2週間後の火曜はお店ごと研修、来週の木曜は鈴木さんがお休み
        $shopOff = $today->addDays(14 + (2 - $today->dayOfWeek + 7) % 7);
        $staffOff = $today->addDays(7 + (4 - $today->dayOfWeek + 7) % 7);
        $shop->dayOffs()->create(['date' => $shopOff->toDateString(), 'note' => '店内研修のため']);
        $shop->dayOffs()->create(['date' => $staffOff->toDateString(), 'staff_id' => $staff[1]->id, 'note' => '講習会']);

        // お客さん(同じ人が何回か来る)
        $names = ['山田 花子', '田中 ゆい', '中村 さき', '小林 まこと', '加藤 りな', '吉田 けんた', '山本 なつき', '松本 ひかる',
            '井上 あや', '木村 そうた', '林 ももか', '清水 はると', '森 ちひろ', '池田 ゆうと', '橋本 かな', '石川 りょう',
            '前田 まい', '藤田 しおり', '岡田 だいき', '後藤 えみ', '長谷川 みお', '村上 ゆうき', '近藤 あかり', '坂本 れい'];
        $customers = collect($names)->map(fn ($n, $i) => ['name' => $n, 'phone' => sprintf('090-0000-%04d', 101 + $i)]);
        $notes = [null, null, null, null, '前髪は長めに残したいです', '肩くらいまで切りたいです', '明るめの色にしたいです', '子ども連れでも大丈夫ですか?'];
        $shopNotes = [null, null, null, 'カラー剤:アッシュ7レベル', '前回より少し短めが希望', 'お仕事の話が好き'];
        $now = CarbonImmutable::now();

        for ($offset = -21; $offset <= 14; $offset++) {
            $day = $today->addDays($offset);
            $hours = $shop->hoursFor($day->dayOfWeek);
            if (! $hours || $day->isSameDay($shopOff)) {
                continue;
            }
            // 先の日ほど予約を少なくして、予約ページに空きが見えるようにする
            $fill = $offset < 0 ? 0.8 : ($offset <= 3 ? 0.65 : ($offset <= 7 ? 0.45 : 0.25));

            foreach ($staff as $member) {
                if (! $member->worksOn($day->dayOfWeek) || ($day->isSameDay($staffOff) && $member->id === $staff[1]->id)) {
                    continue;
                }
                $t = $day->setTimeFromTimeString($hours[0]);
                $close = $day->setTimeFromTimeString($hours[1]);
                while (true) {
                    $t = $t->addMinutes(30 * mt_rand(0, 2)); // 予約の間の空き
                    $menuIndex = [0, 0, 0, 1, 1, 2, 4, 5, 3][mt_rand(0, 8)];
                    if ($menuIndex === 3 && $member->id === $staff[2]->id) {
                        $menuIndex = 0;
                    }
                    $menu = $menus[$menuIndex];
                    if ($t->addMinutes($menu->duration_minutes)->gt($close)) {
                        break;
                    }
                    if (mt_rand(1, 100) / 100 > $fill) {
                        $t = $t->addMinutes(60);

                        continue;
                    }

                    $c = $customers[mt_rand(0, $customers->count() - 1)];
                    $end = $t->addMinutes($menu->duration_minutes);
                    $past = $end->lt($now);
                    $status = 'confirmed';
                    if ($past) {
                        $roll = mt_rand(1, 100);
                        $status = $roll <= 4 ? 'no_show' : ($roll <= 9 ? 'cancelled' : 'visited');
                        if ($offset === -1 && $roll > 90) {
                            $status = 'confirmed'; // 昨日の分に「来店の記録なし」を少し残す
                        }
                    } elseif (mt_rand(1, 100) <= 4) {
                        $status = 'cancelled';
                    }
                    $web = mt_rand(1, 100) <= 65;

                    $r = new Reservation([
                        'staff_id' => $member->id,
                        'menu_id' => $menu->id,
                        'menu_name' => $menu->name,
                        'price' => $menu->price,
                        'duration_minutes' => $menu->duration_minutes,
                        'starts_at' => $t,
                        'ends_at' => $end,
                        'customer_name' => $c['name'],
                        'customer_phone' => $c['phone'],
                        'customer_note' => $web ? $notes[mt_rand(0, count($notes) - 1)] : null,
                        'shop_note' => $status === 'visited' ? $shopNotes[mt_rand(0, count($shopNotes) - 1)] : null,
                        'status' => $status,
                        'source' => $web ? 'web' : 'shop',
                        'nominated' => mt_rand(1, 100) <= 40,
                    ]);
                    $r->user()->associate($shop);
                    // 受付日時:予約の1〜10日前(未来の予約の一部は「最近入ったネット予約」に出るよう、ここ数日)
                    $created = $t->subDays(mt_rand(1, 10))->setTime(mt_rand(8, 22), mt_rand(0, 59));
                    if ($created->gt($now)) {
                        $created = $now->subHours(mt_rand(1, 60));
                    }
                    $r->created_at = $created;
                    $r->cancelled_at = $status === 'cancelled' ? $created->addHours(mt_rand(2, 30))->min($now) : null;
                    $r->save();

                    $t = $end;
                }
            }
        }
    }
}
