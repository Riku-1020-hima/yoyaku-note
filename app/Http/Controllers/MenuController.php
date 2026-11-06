<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(Request $request): View
    {
        return view('menus.index', ['menus' => $request->user()->menus()->ordered()->withCount('staff')->get()]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('menus.form', [
            'menu' => new Menu(['duration_minutes' => 60, 'is_active' => true, 'sort' => (Menu::where('user_id', $user->id)->max('sort') ?? 0) + 1]),
            'staff' => $user->staff()->ordered()->get(),
            // 新しいメニューは、はじめは全員ができることにしておく
            'checked' => $user->staff()->pluck('id')->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $menu = $request->user()->menus()->create($this->validated($request));
        $menu->staff()->sync($this->staffIds($request));

        return redirect()->route('menus.index')->with('status', "「{$menu->name}」を追加しました。");
    }

    public function edit(Request $request, Menu $menu): View
    {
        $this->own($menu);

        return view('menus.form', [
            'menu' => $menu,
            'staff' => $request->user()->staff()->ordered()->get(),
            'checked' => $menu->staff()->pluck('staff.id')->all(),
        ]);
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $this->own($menu);
        $menu->update($this->validated($request));
        $menu->staff()->sync($this->staffIds($request));

        return redirect()->route('menus.index')->with('status', "「{$menu->name}」を保存しました。");
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $this->own($menu);
        $menu->delete(); // 予約にはメニューの名前・料金を写してあるので、過去の予約は残る

        return redirect()->route('menus.index')->with('status', "「{$menu->name}」を削除しました。");
    }

    private function validated(Request $request): array
    {
        if ($request->filled('price')) {
            $request->merge(['price' => preg_replace('/[^\d]/', '', mb_convert_kana((string) $request->input('price'), 'n'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:600'],
            'price' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ], [], ['name' => 'メニュー名', 'duration_minutes' => '所要時間', 'price' => '料金', 'description' => '説明', 'sort' => '並び順']);

        return array_merge($data, [
            'price' => (int) ($data['price'] ?? 0),
            'sort' => (int) ($data['sort'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ]);
    }

    /** チェックした担当者(自分のお店の人だけ) */
    private function staffIds(Request $request): array
    {
        $ids = array_map('intval', (array) $request->input('staff_ids', []));

        return $request->user()->staff()->whereIn('id', $ids)->pluck('id')->all();
    }
}
