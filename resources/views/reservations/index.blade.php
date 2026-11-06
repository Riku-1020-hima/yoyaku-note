@extends('layouts.app', ['title' => '予約表'])

@use('App\Models\Menu')
@use('App\Models\Reservation')
@use('App\Models\User')

@php
    $dayLabel = $date->format('Y年n月j日').'('.User::WEEKDAYS[$date->dayOfWeek].')';
    // 予約の色(状態ごと)
    $tone = fn (Reservation $r) => match ($r->status) {
        'visited' => 'bg-stone-100 border-stone-300 text-stone-600',
        'no_show' => 'bg-alert-50 border-alert-500 text-alert-600',
        default => $r->source === 'web' ? 'bg-brand-50 border-brand-500 text-ink' : 'bg-sky-50 border-sky-500 text-ink',
    };
@endphp

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h1 class="text-2xl font-bold">予約表</h1>
    <a href="{{ route('reservations.create', ['date' => $date->toDateString()]) }}" data-tour="res-new" class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-4 py-2 text-sm">+ 予約を入れる</a>
</div>

{{-- 日付の切り替え --}}
<div data-tour="res-days" class="bg-white rounded-xl border border-stone-200 p-3 mb-4">
    <div class="flex flex-wrap items-center gap-2 mb-3">
        <a href="{{ route('reservations.index', ['date' => $date->subDay()->toDateString()]) }}" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm" aria-label="前の日">←</a>
        <p class="font-bold">{{ $dayLabel }}</p>
        <a href="{{ route('reservations.index', ['date' => $date->addDay()->toDateString()]) }}" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm" aria-label="次の日">→</a>
        @unless ($date->isToday())
            <a href="{{ route('reservations.index') }}" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm">今日</a>
        @endunless
        <form method="GET" action="{{ route('reservations.index') }}" class="ml-auto">
            <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()" aria-label="日付を選ぶ" class="rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
        </form>
    </div>
    <div class="grid grid-cols-7 gap-1 text-center text-xs">
        @for ($i = 0; $i < 7; $i++)
            @php $d = $weekStart->addDays($i); $n = $counts[$d->toDateString()] ?? 0; @endphp
            <a href="{{ route('reservations.index', ['date' => $d->toDateString()]) }}"
               class="rounded-lg py-1.5 {{ $d->isSameDay($date) ? 'bg-brand-500 text-white font-bold' : 'hover:bg-stone-100' }}"
               @if ($d->isSameDay($date)) aria-current="date" @endif>
                <span class="block {{ ! $d->isSameDay($date) && $i === 0 ? 'text-alert-600' : (! $d->isSameDay($date) && $i === 6 ? 'text-blue-700' : '') }}">{{ User::WEEKDAYS[$i] }}</span>
                <span class="block text-sm">{{ $d->day }}</span>
                <span class="block {{ $d->isSameDay($date) ? '' : 'text-stone-500' }}">{{ $n ? $n.'件' : '–' }}</span>
            </a>
        @endfor
    </div>
</div>

@if (! $hours || $shopClosed)
    <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 text-amber-900 px-4 py-2.5 text-sm">
        この日は{{ $shopClosed ? 'お休み(臨時休業)' : '定休日' }}です。予約ページからは予約できません(お店での入力はできます)。
    </div>
@endif

@error('status')
    <div role="alert" class="mb-4 rounded-lg border border-alert-500 bg-alert-50 text-alert-600 px-4 py-3 text-sm font-bold">{{ $message }}</div>
@enderror

<p class="text-sm text-stone-600 mb-3">{{ $active->count() }}件・売上の見込み {{ Menu::yen($total) }}
    <span class="ml-3 inline-flex items-center gap-1 text-xs"><span class="w-3 h-3 rounded-sm border border-brand-500 bg-brand-50"></span>ネット予約</span>
    <span class="ml-2 inline-flex items-center gap-1 text-xs"><span class="w-3 h-3 rounded-sm border border-sky-500 bg-sky-50"></span>お店で入力</span>
    <span class="ml-2 inline-flex items-center gap-1 text-xs"><span class="w-3 h-3 rounded-sm border border-stone-300 bg-stone-100"></span>来店済み</span>
</p>

@if ($staff->isEmpty())
    <div class="bg-white rounded-xl border border-stone-200">
        <x-empty-state message="担当者を登録すると、ここに担当者ごとの予約表が出ます。" action="担当者を追加" :url="route('staff.create')" />
    </div>
@else
    {{-- パソコン・タブレット:縦が時刻、横が担当者の表 --}}
    <div data-tour="res-board" class="hidden sm:block bg-white rounded-xl border border-stone-200 overflow-x-auto">
        <table class="w-full text-sm border-collapse table-fixed" style="min-width: {{ 80 + $staff->count() * 160 }}px">
            <thead>
                <tr class="bg-stone-50">
                    <th class="w-20 px-2 py-2 text-xs text-stone-500 font-medium sticky left-0 bg-stone-50">時刻</th>
                    @foreach ($staff as $s)
                        @php $off = in_array($s->id, $offStaff, true) || ! $s->worksOn($date->dayOfWeek); @endphp
                        <th class="px-2 py-2 text-left border-l border-stone-200">
                            <span class="font-bold">{{ $s->name }}</span>
                            @if ($off)<span class="ml-1 text-xs text-stone-500 font-normal">休み</span>@endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $i => $t)
                    @php $hourLine = $t->minute === 0; @endphp
                    <tr class="h-7 {{ $hourLine ? 'border-t border-stone-200' : 'border-t border-stone-100 border-dashed' }}">
                        <th class="px-2 text-xs font-normal text-stone-500 align-top sticky left-0 bg-white">{{ $hourLine || $t->minute === 30 ? $t->format('G:i') : '' }}</th>
                        @foreach ($staff as $s)
                            @php
                                $cell = $cells[$s->id][$i] ?? null;
                                $covered = false;
                                foreach ($cells[$s->id] ?? [] as $startRow => $c) {
                                    if ($startRow < $i && $startRow + $c['span'] > $i) { $covered = true; break; }
                                }
                                $off = in_array($s->id, $offStaff, true) || ! $s->worksOn($date->dayOfWeek) || ! $hours || $shopClosed;
                            @endphp
                            @if ($cell)
                                @php $r = $cell['reservation']; @endphp
                                <td rowspan="{{ $cell['span'] }}" class="border-l border-stone-200 p-0.5 align-top">
                                    <a href="{{ route('reservations.show', $r) }}" class="block h-full min-h-full rounded-md border-l-4 px-2 py-1 {{ $tone($r) }} hover:brightness-95" style="height: {{ $cell['span'] * 28 - 4 }}px">
                                        <span class="block text-xs">{{ $r->starts_at->format('G:i') }}〜{{ $r->ends_at->format('G:i') }}@if ($r->nominated)<span class="ml-1 font-bold">指名</span>@endif</span>
                                        <span class="block font-bold truncate">{{ $r->customer_name }}様</span>
                                        @if ($cell['span'] >= 3)<span class="block text-xs truncate">{{ $r->menu_name }}</span>@endif
                                        @if ($r->status !== 'confirmed')<span class="block text-xs font-bold">{{ $r->status_label }}</span>@endif
                                    </a>
                                </td>
                            @elseif (! $covered)
                                <td class="border-l border-stone-200 p-0 {{ $off ? 'bg-stone-50' : '' }}">
                                    <a href="{{ route('reservations.create', ['date' => $date->toDateString(), 'staff' => $s->id, 'time' => $t->format('H:i')]) }}"
                                       class="group block h-7 text-center text-xs text-transparent hover:text-brand-700 hover:bg-brand-50 focus-visible:text-brand-700"
                                       aria-label="{{ $s->name }}さん {{ $t->format('G:i') }}に予約を入れる">+ {{ $t->format('G:i') }}</a>
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- スマホ:担当者ごとの予約の一覧 --}}
    <div class="sm:hidden space-y-4">
        @foreach ($staff as $s)
            @php $mine = $active->where('staff_id', $s->id); $off = in_array($s->id, $offStaff, true) || ! $s->worksOn($date->dayOfWeek); @endphp
            <section class="bg-white rounded-xl border border-stone-200 p-4">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="font-bold">{{ $s->name }}@if ($off)<span class="ml-1 text-xs text-stone-500 font-normal">休み</span>@endif</h2>
                    <a href="{{ route('reservations.create', ['date' => $date->toDateString(), 'staff' => $s->id]) }}" class="text-sm text-brand-700 underline">+ 予約</a>
                </div>
                @if ($mine->isEmpty())
                    <p class="text-sm text-stone-500">予約はありません。</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($mine as $r)
                            <li>
                                <a href="{{ route('reservations.show', $r) }}" class="block rounded-lg border-l-4 px-3 py-2 {{ $tone($r) }}">
                                    <span class="text-sm font-bold">{{ $r->starts_at->format('G:i') }}〜{{ $r->ends_at->format('G:i') }}</span>
                                    <span class="ml-2 font-bold">{{ $r->customer_name }}様</span>
                                    <span class="block text-xs">{{ $r->menu_name }}@if ($r->nominated)・指名@endif @if ($r->status !== 'confirmed')・{{ $r->status_label }}@endif</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>
@endif

@if ($unassigned->isNotEmpty())
    <section class="mt-5 bg-white rounded-xl border border-amber-300 p-4">
        <h2 class="font-bold text-amber-900">担当者が決まっていない予約</h2>
        <p class="text-xs text-stone-500 mb-2">担当者を削除したときなどに出ます。予約を開いて担当者を選んでください。</p>
        <ul class="text-sm space-y-1">
            @foreach ($unassigned as $r)
                <li><a href="{{ route('reservations.show', $r) }}" class="underline">{{ $r->starts_at->format('G:i') }} {{ $r->customer_name }}様({{ $r->menu_name }})</a></li>
            @endforeach
        </ul>
    </section>
@endif

@if ($cancelled->isNotEmpty())
    <details class="mt-5 bg-white rounded-xl border border-stone-200 p-4">
        <summary class="font-bold cursor-pointer">この日のキャンセル({{ $cancelled->count() }}件)</summary>
        <ul class="mt-2 text-sm space-y-1">
            @foreach ($cancelled as $r)
                <li><a href="{{ route('reservations.show', $r) }}" class="text-stone-500 underline">{{ $r->starts_at->format('G:i') }} {{ $r->customer_name }}様({{ $r->menu_name }}・{{ $r->staff?->name ?? '担当なし' }})</a></li>
            @endforeach
        </ul>
    </details>
@endif
@endsection
