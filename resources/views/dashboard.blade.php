@extends('layouts.app', ['title' => 'ホーム'])

@use('App\Models\Menu')
@use('App\Models\Reservation')
@use('Carbon\CarbonImmutable')

@section('content')
@unless (collect($setup)->every('done'))
    <section data-tour="setup" class="mb-6 rounded-xl border border-brand-100 bg-brand-50 p-5">
        <h2 class="font-bold text-brand-700">はじめにやること</h2>
        <ol class="mt-3 space-y-2 text-sm">
            @foreach ($setup as $i => $step)
                <li class="flex items-center gap-3">
                    <span class="inline-flex w-6 h-6 shrink-0 rounded-full items-center justify-center text-xs font-bold {{ $step['done'] ? 'bg-brand-500 text-white' : 'bg-white border border-brand-100 text-brand-700' }}">{{ $step['done'] ? '✓' : $i + 1 }}</span>
                    @if ($step['done'])
                        <span class="text-stone-400 line-through">{{ $step['label'] }}</span>
                    @else
                        <a href="{{ $step['url'] }}" class="underline decoration-brand-100 underline-offset-4 hover:text-brand-700">{{ $step['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
@endunless

<div data-tour="numbers" class="grid sm:grid-cols-3 gap-4 mb-6">
    <a href="{{ route('reservations.index') }}" class="block bg-white rounded-xl border border-stone-200 p-5 hover:border-stone-300">
        <p class="text-sm text-stone-500">今日の予約</p>
        <p class="mt-1 text-2xl font-bold">{{ $todayList->count() }}件</p>
        <p class="mt-1 text-xs text-stone-500">明日は{{ $tomorrowCount }}件</p>
    </a>
    <div class="bg-white rounded-xl border border-stone-200 p-5">
        <p class="text-sm text-stone-500">{{ $month->format('n') }}月の売上の見込み</p>
        <p class="mt-1 text-2xl font-bold">{{ Menu::yen((int) $monthTotal) }}</p>
        <p class="mt-1 text-xs text-stone-500">キャンセルを除いた予約の合計</p>
    </div>
    @if ($unrecorded)
        <a href="{{ route('reservations.index', ['date' => CarbonImmutable::parse($unrecordedFirst)->toDateString()]) }}" class="block bg-white rounded-xl border border-amber-300 p-5 hover:border-amber-400">
            <p class="text-sm text-amber-800">来店の記録がまだの予約</p>
            <p class="mt-1 text-2xl font-bold">{{ $unrecorded }}件</p>
            <p class="mt-1 text-xs text-stone-500">「来店済み」にすると、お客さんの来店回数に数えます</p>
        </a>
    @else
        <div class="bg-white rounded-xl border border-stone-200 p-5">
            <p class="text-sm text-stone-500">来店の記録</p>
            <p class="mt-1 text-2xl font-bold">✓</p>
            <p class="mt-1 text-xs text-stone-500">終わった予約は全部記録されています</p>
        </div>
    @endif
</div>

<div class="grid lg:grid-cols-2 gap-5">
    <section data-tour="today" class="bg-white rounded-xl border border-stone-200 p-5">
        <div class="flex items-center justify-between">
            <h2 class="font-bold">今日の予約</h2>
            <a href="{{ route('reservations.index') }}" class="text-sm text-brand-700 underline">予約表を開く</a>
        </div>
        @if ($todayList->isEmpty())
            <p class="mt-3 text-sm text-stone-500">今日の予約はありません。</p>
        @else
            <ul class="mt-3 divide-y divide-stone-100 text-sm">
                @foreach ($todayList as $r)
                    <li class="py-2.5">
                        <a href="{{ route('reservations.show', $r) }}" class="flex items-center gap-3 hover:underline">
                            <span class="w-24 shrink-0 font-bold">{{ $r->starts_at->format('G:i') }}〜{{ $r->ends_at->format('G:i') }}</span>
                            <span class="min-w-0">
                                <span class="font-bold">{{ $r->customer_name }}様</span>
                                <span class="block text-xs text-stone-500 truncate">{{ $r->menu_name }}・{{ $r->staff?->name ?? '担当なし' }}</span>
                            </span>
                            <x-res-status :reservation="$r" class="ml-auto shrink-0" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="space-y-5">
        <section data-tour="booking-url" class="bg-white rounded-xl border border-stone-200 p-5">
            <h2 class="font-bold">お客さんの予約ページ</h2>
            <p class="text-sm text-stone-600 mt-1">このURLを、Instagramのプロフィールや、LINE公式アカウントのメニューに貼ってください。24時間、予約を受け付けます。</p>
            <input type="text" readonly value="{{ auth()->user()->booking_url }}" onclick="this.select()" aria-label="予約ページのURL" class="mt-3 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm bg-stone-50 font-mono">
            <div class="mt-2 flex flex-wrap gap-2">
                <button type="button" data-copy="{{ auth()->user()->booking_url }}" onclick="copyText(this)" class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-bold">URLをコピー</button>
                <a href="{{ auth()->user()->booking_url }}" target="_blank" rel="noopener" class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-bold">開いてみる</a>
            </div>
        </section>

        <section data-tour="new-web" class="bg-white rounded-xl border border-stone-200 p-5">
            <h2 class="font-bold">最近入ったネット予約</h2>
            <p class="text-xs text-stone-500 mt-0.5">この3日間に入った予約を、日付の近い順に出しています</p>
            @if ($newWeb->isEmpty())
                <p class="mt-3 text-sm text-stone-500">この3日間に入った、これからの予約はありません。</p>
            @else
                {{-- 日付ごとにまとめて、その下に時刻を並べる --}}
                <div class="mt-3 space-y-3 text-sm">
                    @foreach ($newWeb->groupBy(fn ($r) => $r->starts_at->toDateString()) as $items)
                        <div>
                            <h3 class="font-bold text-brand-700 border-b border-stone-100 pb-1">{{ Reservation::dayLabel($items->first()->starts_at) }}</h3>
                            <ul>
                                @foreach ($items as $r)
                                    <li>
                                        <a href="{{ route('reservations.show', $r) }}" class="flex items-center gap-3 py-1.5 hover:bg-stone-50 rounded {{ $r->isCancelled() ? 'text-stone-400' : '' }}">
                                            <span class="w-11 shrink-0 font-bold tabular-nums">{{ $r->starts_at->format('G:i') }}</span>
                                            <span class="min-w-0 flex-1">
                                                <span class="font-bold {{ $r->isCancelled() ? 'line-through' : '' }}">{{ $r->customer_name }}様</span>
                                                <span class="block text-xs text-stone-500 truncate">{{ $r->menu_name }}・{{ $r->staff?->name ?? '担当なし' }}</span>
                                            </span>
                                            @if ($r->isCancelled())
                                                <span class="shrink-0 text-xs">キャンセル済み</span>
                                            @elseif ($r->created_at->gte(now()->subDay()))
                                                <span class="shrink-0 rounded-full bg-brand-500 text-white text-xs font-bold px-2 py-0.5">新着</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
