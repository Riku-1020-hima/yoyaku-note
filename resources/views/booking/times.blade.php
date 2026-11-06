@extends('layouts.app', ['title' => '日時を選ぶ', 'publicPage' => true, 'shopName' => $shop->name])

@use('App\Models\Menu')
@use('App\Models\User')

@php
    $staffParam = $nominated?->id ?? 'any';
    // カレンダーは日曜はじまり。受付期間を含む週をまるごと並べる
    $calStart = $first->subDays($first->dayOfWeek);
    $calEnd = $last->addDays(6 - $last->dayOfWeek);
@endphp

@section('content')
<div class="max-w-xl mx-auto">
    <x-shop-header :shop="$shop" :step="2" />

    <a href="{{ route('booking.shop', $shop->slug) }}" class="text-sm text-stone-500 hover:text-ink">← メニューを選び直す</a>
    <div class="mt-2 mb-5 bg-white rounded-xl border border-stone-200 px-4 py-3 flex items-center justify-between gap-3">
        <div>
            <p class="font-bold">{{ $menu->name }}</p>
            <p class="text-sm text-stone-500">所要時間 {{ $menu->duration_label }}</p>
        </div>
        <p class="font-bold">{{ Menu::yen($menu->price) }}</p>
    </div>

    @error('start')
        <div role="alert" class="mb-5 rounded-lg border border-alert-500 bg-alert-50 text-alert-600 px-4 py-3 text-sm font-bold">{{ $message }}</div>
    @enderror

    @if ($candidates->isEmpty())
        <p class="bg-white rounded-xl border border-stone-200 p-5 text-sm text-stone-500">このメニューは、ただいまネット予約を受け付けていません。お店にお電話ください。</p>
    @else
        {{-- 担当者 --}}
        <section class="mb-5">
            <h2 class="font-bold mb-2">担当者</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('booking.times', [$shop->slug, $menu->id, 'staff' => 'any', 'date' => $date->toDateString()]) }}"
                   class="rounded-full border px-4 py-2 text-sm {{ ! $nominated ? 'bg-brand-500 border-brand-500 text-white font-bold' : 'bg-white border-stone-300 hover:border-brand-500' }}"
                   @if (! $nominated) aria-current="true" @endif>指名なし</a>
                @foreach ($candidates as $s)
                    <a href="{{ route('booking.times', [$shop->slug, $menu->id, 'staff' => $s->id, 'date' => $date->toDateString()]) }}"
                       class="rounded-full border px-4 py-2 text-sm {{ $nominated?->id === $s->id ? 'bg-brand-500 border-brand-500 text-white font-bold' : 'bg-white border-stone-300 hover:border-brand-500' }}"
                       @if ($nominated?->id === $s->id) aria-current="true" @endif>
                        {{ $s->name }}@if ($s->title)<span class="text-xs opacity-80 ml-1">{{ $s->title }}</span>@endif
                    </a>
                @endforeach
            </div>
            @unless ($nominated)
                <p class="text-xs text-stone-500 mt-2">「指名なし」は、空いている担当者の中からお店が決めます。いちばん多くの時間から選べます。</p>
            @endunless
        </section>

        {{-- 日付 --}}
        <section class="mb-5 bg-white rounded-xl border border-stone-200 p-3 sm:p-4">
            <h2 class="font-bold mb-1">日付</h2>
            <p class="text-xs text-stone-500 mb-3">○ 空きあり △ 残りわずか × 満席・休み</p>
            <div class="grid grid-cols-7 gap-1 text-center text-xs">
                @foreach (User::WEEKDAYS as $w => $label)
                    <div class="py-1 font-bold {{ $w === 0 ? 'text-alert-600' : ($w === 6 ? 'text-blue-700' : 'text-stone-500') }}">{{ $label }}</div>
                @endforeach
                @for ($d = $calStart; $d->lte($calEnd); $d = $d->addDay())
                    @php
                        $key = $d->toDateString();
                        $inRange = array_key_exists($key, $days);
                        $count = $inRange ? count($days[$key]) : 0;
                        $selected = $d->isSameDay($date);
                        $mark = ! $inRange ? '' : ($count === 0 ? '×' : ($count <= 2 ? '△' : '○'));
                    @endphp
                    @if (! $inRange)
                        <div class="py-2 text-stone-300">{{ $d->day }}</div>
                    @elseif ($count === 0)
                        <div class="py-2 rounded-lg text-stone-400 {{ $selected ? 'ring-2 ring-stone-300' : '' }}" aria-label="{{ $d->format('n月j日') }} 予約できません">
                            <span class="block">{{ $d->day }}</span><span class="block">×</span>
                        </div>
                    @else
                        <a href="{{ route('booking.times', [$shop->slug, $menu->id, 'staff' => $staffParam, 'date' => $key]) }}#times"
                           class="py-2 rounded-lg font-bold {{ $selected ? 'bg-brand-500 text-white' : 'bg-brand-50 text-brand-700 hover:bg-brand-100' }}"
                           aria-label="{{ $d->format('n月j日') }} 空きあり" @if ($selected) aria-current="date" @endif>
                            <span class="block">{{ $d->day }}</span><span class="block">{{ $mark }}</span>
                        </a>
                    @endif
                @endfor
            </div>
        </section>

        {{-- 時刻 --}}
        <section id="times" class="bg-white rounded-xl border border-stone-200 p-4 scroll-mt-4">
            <h2 class="font-bold mb-3">{{ $date->format('n月j日') }}({{ User::WEEKDAYS[$date->dayOfWeek] }})の空き時間</h2>
            @if (empty($slots))
                <p class="text-sm text-stone-500">この日は予約できる時間がありません。カレンダーの○の日をお選びください。</p>
            @else
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                    @foreach ($slots as $slot)
                        <a href="{{ route('booking.form', [$shop->slug, $menu->id, 'staff' => $staffParam, 'start' => $slot['start']->format('Y-m-d\TH:i')]) }}"
                           class="rounded-lg border border-brand-500 text-brand-700 font-bold py-2.5 text-center hover:bg-brand-50">{{ $slot['start']->format('G:i') }}</a>
                    @endforeach
                </div>
                <p class="text-xs text-stone-500 mt-3">終わる時間の目安:開始から{{ $menu->duration_label }}後</p>
            @endif
        </section>
    @endif

    <x-shop-info :shop="$shop" />
</div>
@endsection
