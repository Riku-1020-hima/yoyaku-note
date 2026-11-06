@extends('layouts.app', ['title' => $member->exists ? $member->name.'さんの編集' : '担当者を追加'])

@use('App\Models\User')

@section('content')
<a href="{{ route('staff.index') }}" class="text-sm text-stone-500 hover:text-ink">← 担当者一覧</a>
<h1 class="text-2xl font-bold mt-2 mb-5">{{ $member->exists ? $member->name.'さんの編集' : '担当者を追加' }}</h1>

@error('delete')
    <div role="alert" class="max-w-xl mb-5 rounded-lg border border-alert-500 bg-alert-50 text-alert-600 px-4 py-3 text-sm font-bold">{{ $message }}</div>
@enderror

<form method="POST" action="{{ $member->exists ? route('staff.update', $member) : route('staff.store') }}"
      data-draft="staff-{{ $member->id ?? 'new' }}"
      class="max-w-xl bg-white rounded-xl border border-stone-200 p-6 space-y-4">
    @csrf
    @if ($member->exists) @method('PUT') @endif

    <div class="grid sm:grid-cols-2 gap-4">
        <x-field label="名前" name="name" hint="お客さんの予約ページに出ます">
            <input type="text" name="name" value="{{ old('name', $member->name) }}" required maxlength="50" placeholder="例:佐藤 あおい" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <x-field label="肩書(任意)" name="title">
            <input type="text" name="title" value="{{ old('title', $member->title) }}" maxlength="30" list="titles" placeholder="例:スタイリスト" class="w-full rounded-lg border border-stone-300 px-3 py-2">
            <datalist id="titles"><option value="店長"><option value="スタイリスト"><option value="アシスタント"><option value="セラピスト"></datalist>
        </x-field>
    </div>

    <fieldset>
        <legend class="text-sm font-medium mb-1">出勤する曜日</legend>
        @php $days = array_map('intval', old('work_weekdays', $member->work_weekdays ?? [])); @endphp
        <div class="flex flex-wrap gap-2">
            @foreach (User::WEEKDAYS as $w => $label)
                <label class="inline-flex items-center gap-1.5 rounded-lg border border-stone-300 px-3 py-1.5 text-sm has-[:checked]:bg-brand-50 has-[:checked]:border-brand-500">
                    <input type="checkbox" name="work_weekdays[]" value="{{ $w }}" @checked(in_array($w, $days, true))> {{ $label }}
                </label>
            @endforeach
        </div>
        <p class="text-xs text-stone-500 mt-1">お店が休みの曜日は、チェックしても予約は入りません。</p>
        @error('work_weekdays')<span class="block text-xs text-alert-600 mt-1">{{ $message }}</span>@enderror
    </fieldset>

    <fieldset>
        <legend class="text-sm font-medium mb-1">できるメニュー</legend>
        @if ($menus->isEmpty())
            <p class="text-sm text-stone-500">まだメニューがありません。<a href="{{ route('menus.create') }}" class="text-brand-700 underline">メニューを追加</a>すると選べます。</p>
        @else
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach ($menus as $menu)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="menu_ids[]" value="{{ $menu->id }}" @checked(in_array($menu->id, array_map('intval', old('menu_ids', $checked)), true))>
                        {{ $menu->name }}
                    </label>
                @endforeach
            </div>
        @endif
    </fieldset>

    <x-field label="並び順" name="sort" hint="小さい数字ほど、予約表と予約ページで左・上に出ます">
        <input type="number" name="sort" value="{{ old('sort', $member->sort) }}" min="0" max="999" class="w-28 rounded-lg border border-stone-300 px-3 py-2">
    </x-field>
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $member->is_active))>
        在籍中(外すと、予約ページと予約表から外れます。過去の予約は残ります)
    </label>

    <div class="flex flex-wrap gap-2 pt-2">
        <button class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-5 py-2.5">{{ $member->exists ? '保存する' : '追加する' }}</button>
        <a href="{{ route('staff.index') }}" data-discard class="rounded-lg border border-stone-300 bg-white px-5 py-2.5">キャンセル</a>
    </div>
</form>

@if ($member->exists)
    <form method="POST" action="{{ route('staff.destroy', $member) }}" class="max-w-xl mt-6 text-right"
          onsubmit="return confirm('{{ $member->name }}さんを削除します。辞めた人は「在籍中」を外すだけにするのがおすすめです。削除しますか?')">
        @csrf @method('DELETE')
        <button class="text-sm text-alert-600 underline">この担当者を削除する</button>
    </form>
@endif
@endsection
