@extends('layouts.app', ['title' => 'お客さま情報', 'publicPage' => true, 'shopName' => $shop->name])

@use('App\Models\Menu')
@use('App\Models\Reservation')

@section('content')
<div class="max-w-xl mx-auto">
    <x-shop-header :shop="$shop" :step="3" />

    <a href="{{ route('booking.times', [$shop->slug, $menu->id, 'staff' => $nominated?->id ?? 'any', 'date' => $start->toDateString()]) }}" class="text-sm text-stone-500 hover:text-ink">← 日時を選び直す</a>

    <section class="mt-2 mb-5 bg-white rounded-xl border border-stone-200 p-4">
        <h2 class="font-bold mb-2">ご予約の内容</h2>
        <dl class="grid grid-cols-[5rem_1fr] gap-y-1.5 text-sm">
            <dt class="text-stone-500">日時</dt>
            <dd class="font-bold">{{ Reservation::dayLabel($start) }} {{ $start->format('G:i') }}〜{{ $start->addMinutes($menu->duration_minutes)->format('G:i') }}</dd>
            <dt class="text-stone-500">メニュー</dt><dd>{{ $menu->name }}({{ $menu->duration_label }})</dd>
            <dt class="text-stone-500">料金</dt><dd>{{ Menu::yen($menu->price) }}</dd>
            <dt class="text-stone-500">担当者</dt><dd>{{ $nominated ? $nominated->name.'(指名)' : '指名なし' }}</dd>
        </dl>
    </section>

    <form method="POST" action="{{ route('booking.store', [$shop->slug, $menu->id]) }}" class="bg-white rounded-xl border border-stone-200 p-5 space-y-4">
        @csrf
        <input type="hidden" name="start" value="{{ $start->format('Y-m-d\TH:i') }}">
        <input type="hidden" name="staff" value="{{ $nominated?->id ?? 'any' }}">

        <x-field label="お名前" name="customer_name">
            <input type="text" name="customer_name" value="{{ old('customer_name') }}" required maxlength="50" autocomplete="name" placeholder="例:山田 花子" class="w-full rounded-lg border border-stone-300 px-3 py-2.5">
        </x-field>
        <x-field label="電話番号" name="customer_phone" hint="ご予約の確認でお電話することがあります">
            <input type="tel" name="customer_phone" value="{{ old('customer_phone') }}" required maxlength="30" autocomplete="tel" inputmode="tel" placeholder="例:090-1234-5678" class="w-full rounded-lg border border-stone-300 px-3 py-2.5">
        </x-field>
        <x-field label="ご要望(任意)" name="customer_note">
            <textarea name="customer_note" rows="3" maxlength="500" placeholder="例:前髪は長めに残したいです" class="w-full rounded-lg border border-stone-300 px-3 py-2.5">{{ old('customer_note') }}</textarea>
        </x-field>

        <div class="rounded-lg bg-stone-50 px-4 py-3 text-sm">
            <p class="font-bold">キャンセルについて</p>
            <p class="text-stone-600 mt-1">ご予約の{{ $shop->cancel_deadline_hours }}時間前までは、予約完了後のページからキャンセルできます。それより後は、お店にお電話ください{{ $shop->phone ? '('.$shop->phone.')' : '' }}。</p>
            <label class="mt-2 flex items-start gap-2">
                <input type="checkbox" name="agree" value="1" @checked(old('agree')) class="mt-1">
                <span>確認しました</span>
            </label>
            @error('agree')<span class="block text-xs text-alert-600 mt-1">{{ $message }}</span>@enderror
        </div>

        <button class="w-full rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold py-3">この内容で予約する</button>
    </form>
</div>
@endsection
