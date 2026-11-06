<?php

namespace App\Http\Controllers;

use App\Models\DayOff;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** お休み(臨時休業・担当者の休み)。定休日は設定の営業時間で決める */
class DayOffController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('day-offs.index', [
            'dayOffs' => $user->dayOffs()->with('staff')->whereDate('date', '>=', today())->orderBy('date')->get(),
            'staff' => $user->staff()->active()->ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'date' => ['required', 'date'],
            'staff_id' => ['nullable', Rule::exists('staff', 'id')->where('user_id', $user->id)],
            'note' => ['nullable', 'string', 'max:100'],
        ], [], ['date' => '日付', 'staff_id' => 'だれが休むか', 'note' => 'メモ']);

        $date = CarbonImmutable::parse($data['date'])->startOfDay();
        $staffId = $data['staff_id'] ?? null;

        $exists = $user->dayOffs()->whereDate('date', $date->toDateString())
            ->when($staffId, fn ($q) => $q->where('staff_id', $staffId), fn ($q) => $q->whereNull('staff_id'))->exists();
        if (! $exists) {
            $user->dayOffs()->create(['date' => $date->toDateString(), 'staff_id' => $staffId, 'note' => $data['note'] ?? null]);
        }

        // その日にもう入っている予約は消さない(お客さんへの連絡が必要なので、知らせるだけ)
        $booked = Reservation::where('user_id', $user->id)->where('status', 'confirmed')
            ->whereDate('starts_at', $date->toDateString())
            ->when($staffId, fn ($q) => $q->where('staff_id', $staffId))
            ->count();

        $back = redirect()->route('day-offs.index')->with('status', Reservation::dayLabel($date).'をお休みにしました。この日は予約ページから予約できなくなります。');
        if ($booked) {
            $back->with('warning', "この日にはもう{$booked}件の予約が入っています。予約はそのまま残っているので、必要ならお客さんにご連絡のうえ、予約表から変更・キャンセルしてください。");
        }

        return $back;
    }

    public function destroy(DayOff $dayOff): RedirectResponse
    {
        $this->own($dayOff);
        $dayOff->delete();

        return redirect()->route('day-offs.index')->with('status', 'お休みを取り消しました。');
    }
}
