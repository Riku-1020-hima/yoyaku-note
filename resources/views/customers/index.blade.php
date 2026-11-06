@extends('layouts.app', ['title' => 'お客さん'])

@use('App\Models\Reservation')
@use('Carbon\CarbonImmutable')

@section('content')
<h1 class="text-2xl font-bold mb-1">お客さん</h1>
<p class="text-sm text-stone-500 mb-5">電話番号が同じ予約を、同じお客さんとしてまとめています。</p>

<form method="GET" action="{{ route('customers.index') }}" class="flex gap-2 mb-4 max-w-md">
    <input type="search" name="q" value="{{ $q }}" placeholder="名前か電話番号で探す" class="flex-1 rounded-lg border border-stone-300 px-3 py-2 text-sm">
    <button class="rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-bold">探す</button>
</form>

<div class="bg-white rounded-xl border border-stone-200 overflow-hidden">
    @if ($customers->isEmpty())
        <x-empty-state :message="$q !== '' ? '見つかりませんでした。' : 'まだお客さんがいません。電話番号つきの予約が入ると、ここに出ます。'" />
    @else
        <table class="rtable w-full text-sm">
            <thead class="bg-stone-50 text-stone-500 text-left">
                <tr>
                    <th class="px-4 py-2.5 font-medium">お名前</th>
                    <th class="px-4 py-2.5 font-medium">電話番号</th>
                    <th class="px-4 py-2.5 font-medium text-right">来店</th>
                    <th class="px-4 py-2.5 font-medium">前回の来店</th>
                    <th class="px-4 py-2.5 font-medium">次の予約</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($customers as $c)
                    @php $who = $latest[$c->latest_id] ?? null; @endphp
                    <tr>
                        <td class="rt-main px-4 py-3">
                            <a href="{{ route('customers.show', $c->phone_digits) }}" class="font-bold underline">{{ $who?->customer_name }}様</a>
                            @if ((int) $c->no_shows > 0)<span class="ml-1 text-xs font-bold text-alert-600">無断キャンセル{{ $c->no_shows }}回</span>@endif
                        </td>
                        <td data-label="電話" class="px-4 py-3">{{ $who?->customer_phone }}</td>
                        <td data-label="来店" class="px-4 py-3 text-right">{{ (int) $c->visits }}回</td>
                        <td data-label="前回" class="px-4 py-3">{{ $c->last_visit ? Reservation::dayLabel(CarbonImmutable::parse($c->last_visit)) : '—' }}</td>
                        <td data-label="次の予約" class="px-4 py-3">{{ $c->next_at ? Reservation::dayLabel(CarbonImmutable::parse($c->next_at)).' '.CarbonImmutable::parse($c->next_at)->format('G:i') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<div class="mt-4">{{ $customers->links() }}</div>
@endsection
