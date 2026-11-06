<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        return view('staff.index', ['members' => $request->user()->staff()->ordered()->with(['menus' => fn ($q) => $q->ordered()])->get()]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('staff.form', [
            'member' => new Staff([
                'is_active' => true,
                'work_weekdays' => $user->openWeekdays(), // はじめは、お店が開いている曜日は全部出勤
                'sort' => (Staff::where('user_id', $user->id)->max('sort') ?? 0) + 1,
            ]),
            'menus' => $user->menus()->ordered()->get(),
            'checked' => $user->menus()->pluck('id')->all(), // はじめは全部のメニューができる
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $member = $request->user()->staff()->create($this->validated($request));
        $member->menus()->sync($this->menuIds($request));

        return redirect()->route('staff.index')->with('status', "{$member->name}さんを追加しました。");
    }

    public function edit(Request $request, Staff $member): View
    {
        $this->own($member);

        return view('staff.form', [
            'member' => $member,
            'menus' => $request->user()->menus()->ordered()->get(),
            'checked' => $member->menus()->pluck('menus.id')->all(),
        ]);
    }

    public function update(Request $request, Staff $member): RedirectResponse
    {
        $this->own($member);
        $member->update($this->validated($request));
        $member->menus()->sync($this->menuIds($request));

        return redirect()->route('staff.index')->with('status', "{$member->name}さんを保存しました。");
    }

    public function destroy(Staff $member): RedirectResponse
    {
        $this->own($member);
        if ($member->reservations()->blocking()->where('starts_at', '>=', now())->exists()) {
            return back()->withErrors(['delete' => "{$member->name}さんには、これからの予約が入っています。先に予約の担当者を変えるか、「在籍中」を外してください。"]);
        }
        $member->delete(); // 過去の予約は残る(担当者は空になる)

        return redirect()->route('staff.index')->with('status', "{$member->name}さんを削除しました。");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:30'],
            'work_weekdays' => ['array'],
            'work_weekdays.*' => ['integer', 'between:0,6'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ], [], ['name' => '名前', 'title' => '肩書', 'work_weekdays' => '出勤する曜日', 'sort' => '並び順']);

        return array_merge($data, [
            'work_weekdays' => array_values(array_unique(array_map('intval', $data['work_weekdays'] ?? []))),
            'sort' => (int) ($data['sort'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ]);
    }

    private function menuIds(Request $request): array
    {
        $ids = array_map('intval', (array) $request->input('menu_ids', []));

        return $request->user()->menus()->whereIn('id', $ids)->pluck('id')->all();
    }
}
