@extends('layouts.app', ['title' => 'お休み'])

@use('App\Models\Reservation')

@section('content')
<h1 class="text-2xl font-bold mb-1">お休み</h1>
<p class="text-sm text-stone-500 mb-5">臨時休業や、担当者のお休みを日付で登録します。毎週の定休日は<a href="{{ route('settings.edit') }}" class="underline">設定</a>の営業時間で決めます。</p>

@if (session('warning'))
    <div role="alert" class="mb-5 rounded-lg border border-amber-300 bg-amber-50 text-amber-900 px-4 py-3 text-sm font-bold">{{ session('warning') }}</div>
@endif

<div class="grid lg:grid-cols-[22rem_1fr] gap-5 items-start">
    <form method="POST" action="{{ route('day-offs.store') }}" class="bg-white rounded-xl border border-stone-200 p-5 space-y-4">
        @csrf
        <h2 class="font-bold">お休みを登録</h2>
        <x-field label="日付" name="date">
            <input type="date" name="date" value="{{ old('date', today()->toDateString()) }}" required class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <x-field label="だれが休むか" name="staff_id">
            <select name="staff_id" class="w-full rounded-lg border border-stone-300 px-3 py-2 bg-white">
                <option value="">お店ごと休み(臨時休業)</option>
                @foreach ($staff as $s)
                    <option value="{{ $s->id }}" @selected((string) old('staff_id') === (string) $s->id)>{{ $s->name }}さん</option>
                @endforeach
            </select>
        </x-field>
        <x-field label="メモ(任意)" name="note">
            <input type="text" name="note" value="{{ old('note') }}" maxlength="100" placeholder="例:研修のため" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <button class="w-full rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold py-2.5">お休みにする</button>
    </form>

    <section class="bg-white rounded-xl border border-stone-200 overflow-hidden">
        <h2 class="font-bold px-5 pt-4 pb-2">これからのお休み</h2>
        @if ($dayOffs->isEmpty())
            <p class="px-5 pb-5 text-sm text-stone-500">登録されたお休みはありません。</p>
        @else
            <ul class="divide-y divide-stone-100">
                @foreach ($dayOffs as $off)
                    <li class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
                        <div>
                            <span class="font-bold">{{ Reservation::dayLabel($off->date) }}</span>
                            <span class="ml-2 {{ $off->staff_id ? '' : 'text-alert-600 font-bold' }}">{{ $off->staff ? $off->staff->name.'さん' : 'お店ごと休み' }}</span>
                            @if ($off->note)<span class="block text-xs text-stone-500">{{ $off->note }}</span>@endif
                        </div>
                        <form method="POST" action="{{ route('day-offs.destroy', $off) }}">
                            @csrf @method('DELETE')
                            <button class="text-stone-500 underline">取り消す</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
