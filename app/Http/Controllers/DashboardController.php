<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $today = CarbonImmutable::today();
        $base = fn () => $user->reservations()->with('staff');

        $setup = [
            ['label' => 'お店の電話番号と営業時間を設定する', 'url' => route('settings.edit'), 'done' => filled($user->phone)],
            ['label' => 'メニューを登録する(カット60分など)', 'url' => route('menus.create'), 'done' => $user->menus()->exists()],
            ['label' => '担当者を登録して、できるメニューを選ぶ', 'url' => route('staff.create'),
                'done' => DB::table('menu_staff')->whereIn('staff_id', $user->staff()->select('id'))->exists()],
        ];

        return view('dashboard', [
            'setup' => $setup,
            'todayList' => $base()->blocking()->whereDate('starts_at', $today->toDateString())->orderBy('starts_at')->get(),
            'tomorrowCount' => $user->reservations()->blocking()->whereDate('starts_at', $today->addDay()->toDateString())->count(),
            'monthTotal' => $user->reservations()->blocking()
                ->whereBetween('starts_at', [$today->startOfMonth(), $today->endOfMonth()])->sum('price'),
            'month' => $today,
            // 最近入ったネット予約(これからの予約を、日付の近い順に。24時間以内の受付には「新着」をつける)
            'newWeb' => $base()->where('source', 'web')->where('created_at', '>=', now()->subDays(3))
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')->limit(8)->get(),
            // 終わったのに「予約」のままの予約(来店済みにし忘れ)
            'unrecorded' => $user->reservations()->where('status', 'confirmed')->where('ends_at', '<', now())->count(),
            'unrecordedFirst' => $user->reservations()->where('status', 'confirmed')->where('ends_at', '<', now())->orderBy('starts_at')->value('starts_at'),
        ]);
    }
}
