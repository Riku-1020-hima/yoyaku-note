@extends('layouts.app', ['title' => 'ご予約の確認', 'publicPage' => true, 'shopName' => $shop->name])

@use('App\Models\Menu')

@section('content')
<div class="max-w-xl mx-auto">
    <x-shop-header :shop="$shop" />

    @if (session('booked'))
        <div role="status" class="mb-5 rounded-xl border border-brand-100 bg-brand-50 p-4">
            <p class="font-bold text-brand-700 text-lg">ご予約ありがとうございます</p>
            <p class="text-sm mt-1">ご予約が確定しました。<b>このページのURLを保存</b>しておくと、あとで内容の確認やキャンセルができます。</p>
        </div>
    @endif
    @error('cancel')
        <div role="alert" class="mb-5 rounded-lg border border-alert-500 bg-alert-50 text-alert-600 px-4 py-3 text-sm font-bold">{{ $message }}</div>
    @enderror

    <section class="bg-white rounded-xl border border-stone-200 p-5">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h1 class="font-bold">ご予約の内容</h1>
            <span class="rounded-full px-3 py-0.5 text-xs font-bold {{ $reservation->isCancelled() ? 'bg-stone-100 text-stone-500' : 'bg-brand-50 text-brand-700' }}">
                {{ $reservation->isCancelled() ? 'キャンセル済み' : ($reservation->status === 'confirmed' ? '予約確定' : $reservation->status_label) }}
            </span>
        </div>
        <dl class="grid grid-cols-[5rem_1fr] gap-y-1.5 text-sm {{ $reservation->isCancelled() ? 'text-stone-400 line-through' : '' }}">
            <dt class="text-stone-500">日時</dt><dd class="font-bold">{{ $reservation->when_label }}</dd>
            <dt class="text-stone-500">メニュー</dt><dd>{{ $reservation->menu_name }}</dd>
            <dt class="text-stone-500">料金</dt><dd>{{ Menu::yen($reservation->price) }}</dd>
            <dt class="text-stone-500">担当者</dt><dd>{{ $reservation->staff?->name ?? '未定' }}</dd>
            <dt class="text-stone-500">お名前</dt><dd>{{ $reservation->customer_name }} 様</dd>
            @if ($reservation->customer_note)
                <dt class="text-stone-500">ご要望</dt><dd class="whitespace-pre-line">{{ $reservation->customer_note }}</dd>
            @endif
        </dl>
    </section>

    @unless ($reservation->isCancelled() || $reservation->starts_at->isPast())
        <section class="mt-5 bg-white rounded-xl border border-stone-200 p-5 space-y-3">
            <h2 class="font-bold">このページを保存する</h2>
            <div class="flex flex-wrap gap-2">
                <button type="button" data-copy="{{ $reservation->manage_url }}" onclick="copyText(this)" class="rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-bold">URLをコピー</button>
                <a href="{{ route('booking.ics', $reservation->token) }}" class="rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-bold">カレンダーに追加</a>
            </div>
            <p class="text-xs text-stone-500">スクリーンショットを撮っておくのもおすすめです。</p>
        </section>

        <section class="mt-5 bg-white rounded-xl border border-stone-200 p-5">
            <h2 class="font-bold">キャンセル</h2>
            @if ($reservation->canCancelOnline($shop))
                <p class="text-sm text-stone-600 mt-1">{{ $reservation->cancelDeadline($shop)->format('n月j日 G:i') }}までは、ここからキャンセルできます。</p>
                <form method="POST" action="{{ route('booking.cancel', $reservation->token) }}" class="mt-3"
                      onsubmit="return confirm('この予約をキャンセルします。よろしいですか?')">
                    @csrf
                    <button class="rounded-lg border border-alert-500 text-alert-600 font-bold px-4 py-2 text-sm hover:bg-alert-50">予約をキャンセルする</button>
                </form>
                <p class="text-xs text-stone-500 mt-3">日時を変えたいときは、キャンセルしてから予約し直してください。</p>
            @else
                <p class="text-sm text-stone-600 mt-1">キャンセル・変更は、お店に直接ご連絡ください。@if ($shop->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $shop->phone) }}" class="underline font-bold">{{ $shop->phone }}</a>@endif</p>
            @endif
        </section>
    @endunless

    <p class="mt-6 text-center">
        <a href="{{ route('booking.shop', $shop->slug) }}" class="text-sm text-brand-700 underline">{{ $reservation->isCancelled() ? 'もう一度予約する' : '別の予約をする' }}</a>
    </p>

    <x-shop-info :shop="$shop" />
</div>
@endsection
