@extends('layouts.app', ['title' => $reservation->exists ? '予約の変更' : '予約を入れる'])

@use('App\Models\Menu')

@php
    $back = $reservation->exists ? route('reservations.show', $reservation) : route('reservations.index', ['date' => $reservation->starts_at->toDateString()]);
@endphp

@section('content')
<a href="{{ $back }}" class="text-sm text-stone-500 hover:text-ink">← {{ $reservation->exists ? '予約の詳細' : '予約表' }}</a>
<h1 class="text-2xl font-bold mt-2 mb-1">{{ $reservation->exists ? '予約の変更' : '予約を入れる' }}</h1>
<p class="text-sm text-stone-500 mb-5">{{ $reservation->exists ? '日時や担当者を変えると、お客さんの確認ページにもすぐ反映されます。' : '電話や来店で受けた予約を入れます。同じ担当者の予約と時間が重なるときは入れられません。' }}</p>

@error('time')
    <div role="alert" class="max-w-2xl mb-5 rounded-lg border border-alert-500 bg-alert-50 text-alert-600 px-4 py-3 text-sm font-bold">{{ $message }}</div>
@enderror

@if ($menus->isEmpty() || $staff->isEmpty())
    <div class="max-w-2xl bg-white rounded-xl border border-stone-200">
        <x-empty-state message="予約を入れるには、先にメニューと担当者を登録してください。" :action="$menus->isEmpty() ? 'メニューを追加' : '担当者を追加'" :url="$menus->isEmpty() ? route('menus.create') : route('staff.create')" />
    </div>
@else
<form method="POST" action="{{ $reservation->exists ? route('reservations.update', $reservation) : route('reservations.store') }}"
      data-draft="reservation-{{ $reservation->id ?? 'new' }}"
      class="max-w-2xl bg-white rounded-xl border border-stone-200 p-6 space-y-4">
    @csrf
    @if ($reservation->exists) @method('PUT') @endif

    <div class="grid sm:grid-cols-2 gap-4">
        <x-field label="メニュー" name="menu_id" :hint="$reservation->exists ? 'メニューを変えると、料金と所要時間も今のメニューの内容になります' : null">
            <select name="menu_id" required class="w-full rounded-lg border border-stone-300 px-3 py-2 bg-white">
                @foreach ($menus as $m)
                    <option value="{{ $m->id }}" @selected((int) old('menu_id', $reservation->menu_id) === $m->id)>{{ $m->name }}({{ $m->duration_label }}・{{ Menu::yen($m->price) }})</option>
                @endforeach
            </select>
        </x-field>
        <x-field label="担当者" name="staff_id">
            <select name="staff_id" required class="w-full rounded-lg border border-stone-300 px-3 py-2 bg-white">
                @foreach ($staff as $s)
                    <option value="{{ $s->id }}" @selected((int) old('staff_id', $reservation->staff_id) === $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </x-field>
        <x-field label="日付" name="date">
            <input type="date" name="date" value="{{ old('date', $reservation->starts_at->toDateString()) }}" required class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <x-field label="開始時刻" name="time" hint="終わる時刻は、メニューの所要時間から自動で決まります">
            @php $current = old('time', $reservation->starts_at->format('H:i')); @endphp
            <select name="time" required class="w-full rounded-lg border border-stone-300 px-3 py-2 bg-white">
                @foreach ($times as $t)
                    <option value="{{ $t }}" @selected($current === $t)>{{ ltrim(substr($t, 0, 2), '0') ?: '0' }}:{{ substr($t, 3) }}</option>
                @endforeach
            </select>
        </x-field>
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="nominated" value="0">
        <input type="checkbox" name="nominated" value="1" @checked(old('nominated', $reservation->nominated))>
        指名あり
    </label>

    <div class="grid sm:grid-cols-2 gap-4 pt-2 border-t border-stone-100">
        <x-field label="お名前" name="customer_name">
            <input type="text" name="customer_name" value="{{ old('customer_name', $reservation->customer_name) }}" required maxlength="50" placeholder="例:山田 花子" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <x-field label="電話番号(任意)" name="customer_phone" hint="入れておくと、同じお客さんの来店履歴がまとまります">
            <input type="tel" name="customer_phone" value="{{ old('customer_phone', $reservation->customer_phone) }}" maxlength="30" inputmode="tel" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
    </div>
    <x-field label="お客さんの要望(任意)" name="customer_note">
        <textarea name="customer_note" rows="2" maxlength="500" class="w-full rounded-lg border border-stone-300 px-3 py-2">{{ old('customer_note', $reservation->customer_note) }}</textarea>
    </x-field>
    <x-field label="お店のメモ(任意)" name="shop_note" hint="お客さんには見えません。使ったカラー剤や会話のメモなど">
        <textarea name="shop_note" rows="2" maxlength="1000" class="w-full rounded-lg border border-stone-300 px-3 py-2">{{ old('shop_note', $reservation->shop_note) }}</textarea>
    </x-field>

    <div class="flex flex-wrap gap-2 pt-2">
        <button class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-5 py-2.5">{{ $reservation->exists ? '変更を保存する' : '予約を入れる' }}</button>
        <a href="{{ $back }}" data-discard class="rounded-lg border border-stone-300 bg-white px-5 py-2.5">キャンセル</a>
    </div>
</form>
@endif
@endsection
