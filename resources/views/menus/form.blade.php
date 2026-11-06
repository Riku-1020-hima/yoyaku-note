@extends('layouts.app', ['title' => $menu->exists ? $menu->name.'の編集' : 'メニューを追加'])

@section('content')
<a href="{{ route('menus.index') }}" class="text-sm text-stone-500 hover:text-ink">← メニュー一覧</a>
<h1 class="text-2xl font-bold mt-2 mb-5">{{ $menu->exists ? $menu->name.'の編集' : 'メニューを追加' }}</h1>

<form method="POST" action="{{ $menu->exists ? route('menus.update', $menu) : route('menus.store') }}"
      data-draft="menu-{{ $menu->id ?? 'new' }}"
      class="max-w-xl bg-white rounded-xl border border-stone-200 p-6 space-y-4">
    @csrf
    @if ($menu->exists) @method('PUT') @endif

    <x-field label="メニュー名" name="name">
        <input type="text" name="name" value="{{ old('name', $menu->name) }}" required maxlength="50" placeholder="例:カット+カラー" class="w-full rounded-lg border border-stone-300 px-3 py-2">
    </x-field>
    <div class="grid sm:grid-cols-2 gap-4">
        <x-field label="所要時間" name="duration_minutes" hint="この時間だけ、担当者の予定がふさがります">
            <select name="duration_minutes" class="w-full rounded-lg border border-stone-300 px-3 py-2 bg-white">
                @php $durations = collect([15, 30, 45, 60, 75, 90, 105, 120, 150, 180, 210, 240, (int) old('duration_minutes', $menu->duration_minutes)])->filter()->unique()->sort(); @endphp
                @foreach ($durations as $m)
                    <option value="{{ $m }}" @selected((int) old('duration_minutes', $menu->duration_minutes) === $m)>{{ \App\Models\Menu::durationLabel($m) }}</option>
                @endforeach
            </select>
        </x-field>
        <x-field label="料金(税込)" name="price">
            <div class="flex items-center gap-2">
                <span class="text-stone-500">¥</span>
                <input type="text" name="price" value="{{ old('price', $menu->price) }}" inputmode="numeric" placeholder="4400" class="w-full rounded-lg border border-stone-300 px-3 py-2">
            </div>
        </x-field>
    </div>
    <x-field label="説明(任意)" name="description" hint="予約ページのメニューの下に出ます">
        <textarea name="description" rows="3" maxlength="500" placeholder="例:シャンプー・ブロー込みです" class="w-full rounded-lg border border-stone-300 px-3 py-2">{{ old('description', $menu->description) }}</textarea>
    </x-field>

    <fieldset data-tour="menu-staff">
        <legend class="text-sm font-medium mb-1">このメニューができる担当者</legend>
        @if ($staff->isEmpty())
            <p class="text-sm text-stone-500">まだ担当者がいません。<a href="{{ route('staff.create') }}" class="text-brand-700 underline">担当者を追加</a>すると選べます。</p>
        @else
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach ($staff as $s)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="staff_ids[]" value="{{ $s->id }}" @checked(in_array($s->id, array_map('intval', old('staff_ids', $checked)), true))>
                        {{ $s->name }}@unless ($s->is_active)<span class="text-xs text-stone-400">(休止中)</span>@endunless
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-stone-500 mt-1">だれもチェックしないと、このメニューは予約を受けられません。</p>
        @endif
    </fieldset>

    <div class="grid sm:grid-cols-2 gap-4">
        <x-field label="並び順" name="sort" hint="小さい数字ほど上に出ます">
            <input type="number" name="sort" value="{{ old('sort', $menu->sort) }}" min="0" max="999" class="w-28 rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $menu->is_active))>
        ネット予約で受け付ける(外すと予約ページに出なくなります。お店での入力はできます)
    </label>

    <div class="flex flex-wrap gap-2 pt-2">
        <button class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-5 py-2.5">{{ $menu->exists ? '保存する' : '追加する' }}</button>
        <a href="{{ route('menus.index') }}" data-discard class="rounded-lg border border-stone-300 bg-white px-5 py-2.5">キャンセル</a>
    </div>
</form>

@if ($menu->exists)
    <form method="POST" action="{{ route('menus.destroy', $menu) }}" class="max-w-xl mt-6 text-right"
          onsubmit="return confirm('「{{ $menu->name }}」を削除します。これまでの予約は残ります。削除しますか?')">
        @csrf @method('DELETE')
        <button class="text-sm text-alert-600 underline">このメニューを削除する</button>
    </form>
@endif
@endsection
