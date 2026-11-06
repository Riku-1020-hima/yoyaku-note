@extends('layouts.app', ['title' => $latest->customer_name.'様'])

@use('App\Models\Menu')
@use('App\Models\Reservation')

@section('content')
<a href="{{ route('customers.index') }}" class="text-sm text-stone-500 hover:text-ink">← お客さん一覧</a>
<h1 class="text-2xl font-bold mt-2">{{ $latest->customer_name }}様</h1>
<p class="text-sm text-stone-500 mb-5"><a href="tel:{{ $latest->phone_digits }}" class="underline">{{ $latest->customer_phone }}</a></p>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
    <div class="bg-white rounded-xl border border-stone-200 p-4"><p class="text-xs text-stone-500">来店</p><p class="text-xl font-bold">{{ $stats['visits'] }}回</p></div>
    <div class="bg-white rounded-xl border border-stone-200 p-4"><p class="text-xs text-stone-500">来店の合計金額</p><p class="text-xl font-bold">{{ Menu::yen($stats['spent']) }}</p></div>
    <div class="bg-white rounded-xl border border-stone-200 p-4"><p class="text-xs text-stone-500">前回の来店</p><p class="text-xl font-bold">{{ $stats['last_visit'] ? Reservation::dayLabel($stats['last_visit']) : '—' }}</p></div>
    <div class="bg-white rounded-xl border border-stone-200 p-4"><p class="text-xs text-stone-500">よく担当する人</p><p class="text-xl font-bold">{{ $stats['favorite_staff'] ?? '—' }}</p></div>
</div>
@if ($stats['no_shows'] || $stats['cancels'])
    <p class="mb-5 text-sm {{ $stats['no_shows'] ? 'text-alert-600 font-bold' : 'text-stone-600' }}">無断キャンセル {{ $stats['no_shows'] }}回・キャンセル {{ $stats['cancels'] }}回</p>
@endif

<section class="bg-white rounded-xl border border-stone-200 overflow-hidden mb-5">
    <h2 class="font-bold px-5 pt-4 pb-2">これからの予約</h2>
    @if ($upcoming->isEmpty())
        <p class="px-5 pb-4 text-sm text-stone-500">次の予約はまだありません。</p>
    @else
        <ul class="divide-y divide-stone-100 text-sm">
            @foreach ($upcoming as $r)
                @include('customers._row', ['r' => $r])
            @endforeach
        </ul>
    @endif
</section>

<section class="bg-white rounded-xl border border-stone-200 overflow-hidden">
    <h2 class="font-bold px-5 pt-4 pb-2">これまでの履歴</h2>
    @if ($pastByMonth->isEmpty())
        <p class="px-5 pb-4 text-sm text-stone-500">まだ来店の履歴はありません。</p>
    @else
        @foreach ($pastByMonth as $month => $items)
            <h3 class="px-5 py-1.5 bg-stone-50 text-xs font-bold text-stone-600 border-y border-stone-100">{{ $month }}({{ $items->count() }}件)</h3>
            <ul class="divide-y divide-stone-100 text-sm">
                @foreach ($items as $r)
                    @include('customers._row', ['r' => $r])
                @endforeach
            </ul>
        @endforeach
    @endif
</section>
@endsection
