@extends('layouts.app', ['title' => $reservation->customer_name.'様の予約'])

@use('App\Models\Menu')
@use('App\Models\Reservation')

@section('content')
<a href="{{ route('reservations.index', ['date' => $reservation->starts_at->toDateString()]) }}" class="text-sm text-stone-500 hover:text-ink">← {{ Reservation::dayLabel($reservation->starts_at) }}の予約表</a>

<div class="flex flex-wrap items-center gap-3 mt-2 mb-5">
    <h1 class="text-2xl font-bold">{{ $reservation->customer_name }}様の予約</h1>
    <x-res-status :reservation="$reservation" />
</div>

@error('status')
    <div role="alert" class="max-w-3xl mb-5 rounded-lg border border-alert-500 bg-alert-50 text-alert-600 px-4 py-3 text-sm font-bold">{{ $message }}</div>
@enderror

<div class="grid lg:grid-cols-[1fr_20rem] gap-5 items-start max-w-5xl">
    <div class="space-y-5">
        <section class="bg-white rounded-xl border border-stone-200 p-5">
            <dl class="grid grid-cols-[7rem_1fr] gap-y-2 text-sm">
                <dt class="text-stone-500">日時</dt><dd class="font-bold">{{ $reservation->when_label }}</dd>
                <dt class="text-stone-500">メニュー</dt><dd>{{ $reservation->menu_name }}({{ Menu::durationLabel($reservation->duration_minutes) }})</dd>
                <dt class="text-stone-500">料金</dt><dd>{{ Menu::yen($reservation->price) }}</dd>
                <dt class="text-stone-500">担当者</dt><dd>{{ $reservation->staff?->name ?? '未定' }}{{ $reservation->nominated ? '(指名)' : '' }}</dd>
                <dt class="text-stone-500">電話番号</dt>
                <dd>@if ($reservation->customer_phone)<a href="tel:{{ $reservation->phone_digits }}" class="underline">{{ $reservation->customer_phone }}</a>@else<span class="text-stone-400">なし</span>@endif</dd>
                <dt class="text-stone-500">受付</dt><dd>{{ Reservation::SOURCES[$reservation->source] ?? $reservation->source }}({{ $reservation->created_at->format('n/j G:i') }})</dd>
                @if ($reservation->cancelled_at)
                    <dt class="text-stone-500">キャンセル日時</dt><dd>{{ $reservation->cancelled_at->format('n/j G:i') }}</dd>
                @endif
                <dt class="text-stone-500">お客さんの要望</dt><dd class="whitespace-pre-line">{{ $reservation->customer_note ?: '—' }}</dd>
                <dt class="text-stone-500">お店のメモ</dt><dd class="whitespace-pre-line">{{ $reservation->shop_note ?: '—' }}</dd>
            </dl>
            <a href="{{ route('reservations.edit', $reservation) }}" class="mt-4 inline-block rounded-lg border border-stone-300 px-4 py-2 text-sm font-bold hover:bg-stone-50">日時・内容を変更する</a>
        </section>

        <section class="bg-white rounded-xl border border-stone-200 p-5">
            <h2 class="font-bold">このお客さんの履歴</h2>
            @if ($reservation->phone_digits === '')
                <p class="mt-2 text-sm text-stone-500">電話番号がないので、履歴をまとめられません。</p>
            @elseif ($history->isEmpty())
                <p class="mt-2 text-sm text-stone-500">はじめてのご予約です。</p>
            @else
                <ul class="mt-2 divide-y divide-stone-100 text-sm">
                    @foreach ($history as $h)
                        <li class="py-2 flex flex-wrap items-center gap-x-3">
                            <a href="{{ route('reservations.show', $h) }}" class="font-bold underline">{{ Reservation::dayLabel($h->starts_at) }}</a>
                            <span>{{ $h->menu_name }}</span>
                            <span class="text-stone-500">{{ $h->staff?->name }}</span>
                            <x-res-status :reservation="$h" class="ml-auto" />
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('customers.show', $reservation->phone_digits) }}" class="mt-2 inline-block text-sm text-brand-700 underline">お客さんのページを見る</a>
            @endif
        </section>
    </div>

    <div class="space-y-5">
        <section data-tour="res-status" class="bg-white rounded-xl border border-stone-200 p-5">
            <h2 class="font-bold mb-3">状態を変える</h2>
            <div class="grid gap-2">
                @foreach (['visited' => '来店済みにする', 'confirmed' => '「予約」に戻す', 'no_show' => '無断キャンセルにする', 'cancelled' => 'キャンセルにする'] as $value => $label)
                    @continue($reservation->status === $value)
                    <form method="POST" action="{{ route('reservations.status', $reservation) }}"
                          @if ($value === 'cancelled') onsubmit="return confirm('この予約をキャンセルにします。時間が空いて、ほかの予約が入れられるようになります。よろしいですか?')" @endif>
                        @csrf
                        <input type="hidden" name="status" value="{{ $value }}">
                        <button class="w-full rounded-lg border px-4 py-2 text-sm font-bold {{ $value === 'visited' ? 'bg-brand-500 border-brand-500 text-white hover:bg-brand-600' : ($value === 'cancelled' || $value === 'no_show' ? 'border-alert-500 text-alert-600 hover:bg-alert-50' : 'border-stone-300 hover:bg-stone-50') }}">{{ $label }}</button>
                    </form>
                @endforeach
            </div>
        </section>

        <section class="bg-white rounded-xl border border-stone-200 p-5">
            <h2 class="font-bold">お客さん用の確認ページ</h2>
            <p class="text-sm text-stone-600 mt-1">予約の内容とキャンセルのページです。電話で受けた予約は、このURLをLINEなどで送ってあげると親切です。</p>
            <button type="button" data-copy="{{ $reservation->manage_url }}" onclick="copyText(this)" class="mt-3 rounded-lg border border-stone-300 px-4 py-2 text-sm font-bold">URLをコピー</button>
            <a href="{{ $reservation->manage_url }}" target="_blank" rel="noopener" class="ml-2 text-sm text-brand-700 underline">開いてみる</a>
        </section>
    </div>
</div>
@endsection
