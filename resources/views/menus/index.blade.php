@extends('layouts.app', ['title' => 'メニュー'])

@use('App\Models\Menu')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h1 class="text-2xl font-bold">メニュー</h1>
    <a href="{{ route('menus.create') }}" data-tour="menu-new" class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-4 py-2 text-sm">+ メニューを追加</a>
</div>

<div class="bg-white rounded-xl border border-stone-200 overflow-hidden">
    @if ($menus->isEmpty())
        <x-empty-state message="まだメニューがありません。カット・カラーなど、予約を受けるメニューを登録しましょう。" action="メニューを追加" :url="route('menus.create')" />
    @else
        <table class="rtable w-full text-sm">
            <thead class="bg-stone-50 text-stone-500 text-left">
                <tr>
                    <th class="px-4 py-2.5 font-medium">メニュー</th>
                    <th class="px-4 py-2.5 font-medium">所要時間</th>
                    <th class="px-4 py-2.5 font-medium text-right">料金</th>
                    <th class="px-4 py-2.5 font-medium">できる担当者</th>
                    <th class="px-4 py-2.5 font-medium">ネット予約</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($menus as $menu)
                    <tr class="{{ $menu->is_active ? '' : 'text-stone-400' }}">
                        <td class="rt-main px-4 py-3 font-bold">{{ $menu->name }}</td>
                        <td data-label="所要時間" class="px-4 py-3">{{ $menu->duration_label }}</td>
                        <td data-label="料金" class="px-4 py-3 text-right">{{ Menu::yen($menu->price) }}</td>
                        <td data-label="担当者" class="px-4 py-3">
                            @if ($menu->staff_count === 0)
                                <span class="text-alert-600 font-bold">0人(予約を受けられません)</span>
                            @else
                                {{ $menu->staff_count }}人
                            @endif
                        </td>
                        <td data-label="ネット予約" class="px-4 py-3">{{ $menu->is_active ? '受付中' : '止めている' }}</td>
                        <td class="rt-actions px-4 py-3 text-right"><a href="{{ route('menus.edit', $menu) }}" class="text-brand-700 underline">編集</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<p class="text-xs text-stone-500 mt-3">料金は税込で入れてください。並び順の小さいものから、予約ページの上に出ます。</p>
@endsection
