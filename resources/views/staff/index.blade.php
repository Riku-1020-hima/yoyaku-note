@extends('layouts.app', ['title' => '担当者'])

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h1 class="text-2xl font-bold">担当者</h1>
    <a href="{{ route('staff.create') }}" class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-4 py-2 text-sm">+ 担当者を追加</a>
</div>

<div class="bg-white rounded-xl border border-stone-200 overflow-hidden">
    @if ($members->isEmpty())
        <x-empty-state message="まだ担当者がいません。1人でやっているお店も、自分を担当者として登録してください。" action="担当者を追加" :url="route('staff.create')" />
    @else
        <table class="rtable w-full text-sm">
            <thead class="bg-stone-50 text-stone-500 text-left">
                <tr>
                    <th class="px-4 py-2.5 font-medium">名前</th>
                    <th class="px-4 py-2.5 font-medium">出勤する曜日</th>
                    <th class="px-4 py-2.5 font-medium">できるメニュー</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($members as $m)
                    <tr class="{{ $m->is_active ? '' : 'text-stone-400' }}">
                        <td class="rt-main px-4 py-3">
                            <span class="font-bold">{{ $m->name }}</span>
                            @if ($m->title)<span class="text-xs text-stone-500 ml-1">{{ $m->title }}</span>@endif
                            @unless ($m->is_active)<span class="ml-1 text-xs rounded-full bg-stone-100 px-2 py-0.5">休止中</span>@endunless
                        </td>
                        <td data-label="出勤" class="px-4 py-3">{{ $m->work_days_label }}</td>
                        <td data-label="メニュー" class="px-4 py-3">
                            @if ($m->menus->isEmpty())
                                <span class="text-alert-600">なし</span>
                            @else
                                {{ $m->menus->pluck('name')->join('、') }}
                            @endif
                        </td>
                        <td class="rt-actions px-4 py-3 text-right"><a href="{{ route('staff.edit', $m) }}" class="text-brand-700 underline">編集</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<p class="text-xs text-stone-500 mt-3">急なお休みは「<a href="{{ route('day-offs.index') }}" class="underline">お休み</a>」で、その日だけ登録できます。</p>
@endsection
