@extends('layouts.app', ['title' => '設定'])

@use('App\Models\User')

@section('content')
<h1 class="text-2xl font-bold mb-5">設定</h1>

<form method="POST" action="{{ route('settings.update') }}" data-draft="settings" class="max-w-2xl space-y-5">
    @csrf @method('PUT')

    <section data-tour="set-shop" class="bg-white rounded-xl border border-stone-200 p-6 space-y-4">
        <h2 class="font-bold">お店の情報</h2>
        <p class="text-sm text-stone-500 -mt-2">お客さんの予約ページに表示されます。</p>
        <x-field label="お店の名前" name="name">
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="100" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <div class="grid sm:grid-cols-2 gap-4">
            <x-field label="電話番号" name="phone" hint="キャンセルの連絡先として出ます">
                <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30" class="w-full rounded-lg border border-stone-300 px-3 py-2">
            </x-field>
            <x-field label="住所" name="address">
                <input type="text" name="address" value="{{ old('address', $user->address) }}" maxlength="200" class="w-full rounded-lg border border-stone-300 px-3 py-2">
            </x-field>
        </div>
        <x-field label="お知らせ(任意)" name="notice" hint="予約ページのいちばん上に出ます">
            <textarea name="notice" rows="3" maxlength="1000" placeholder="例:駐車場は2台あります。初めての方はカウンセリングのため10分早めにお越しください。" class="w-full rounded-lg border border-stone-300 px-3 py-2">{{ old('notice', $user->notice) }}</textarea>
        </x-field>
        <x-field label="予約ページのURL" name="slug" :hint="config('demo.enabled') ? 'デモ版では変えられません' : '半角の小文字・数字・ハイフン(-)。変えると、前のURLは使えなくなります'">
            <div class="flex items-center gap-1 text-sm">
                <span class="text-stone-500 whitespace-nowrap">{{ url('/r') }}/</span>
                <input type="text" name="slug" value="{{ old('slug', $user->slug) }}" required maxlength="40" @readonly(config('demo.enabled')) class="w-full rounded-lg border border-stone-300 px-3 py-2 font-mono {{ config('demo.enabled') ? 'bg-stone-50' : '' }}">
            </div>
        </x-field>
    </section>

    <section data-tour="set-hours" class="bg-white rounded-xl border border-stone-200 p-6 space-y-3">
        <h2 class="font-bold">営業時間</h2>
        <p class="text-sm text-stone-500 -mt-1">閉店時間までに終わる予約だけを受け付けます(例:19時閉店なら、60分のメニューは18時が最後)。</p>
        @foreach (User::WEEKDAYS as $w => $label)
            @php
                $h = $user->hoursFor($w);
                $open = (bool) old("hours.$w.open", $h !== null);
            @endphp
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <label class="flex items-center gap-2 w-24">
                    <input type="hidden" name="hours[{{ $w }}][open]" value="0">
                    <input type="checkbox" name="hours[{{ $w }}][open]" value="1" @checked($open)>
                    <span class="font-bold {{ $w === 0 ? 'text-alert-600' : ($w === 6 ? 'text-blue-700' : '') }}">{{ $label }}曜日</span>
                </label>
                <input type="time" name="hours[{{ $w }}][from]" value="{{ old("hours.$w.from", $h[0] ?? '10:00') }}" step="900" aria-label="{{ $label }}曜日の開店" class="rounded-lg border border-stone-300 px-2 py-1.5">
                <span>〜</span>
                <input type="time" name="hours[{{ $w }}][to]" value="{{ old("hours.$w.to", $h[1] ?? '19:00') }}" step="900" aria-label="{{ $label }}曜日の閉店" class="rounded-lg border border-stone-300 px-2 py-1.5">
                @error("hours.$w.to")<span class="w-full text-xs text-alert-600">{{ $message }}</span>@enderror
            </div>
        @endforeach
        <p class="text-xs text-stone-500">チェックを外した曜日は定休日になります。</p>
    </section>

    <section data-tour="set-rules" class="bg-white rounded-xl border border-stone-200 p-6 space-y-4">
        <h2 class="font-bold">予約の受け付けルール</h2>
        <div class="grid sm:grid-cols-2 gap-4">
            <x-field label="予約できる時刻の間隔" name="slot_interval" hint="30分ごとなら 10:00、10:30、11:00…">
                <select name="slot_interval" class="w-full rounded-lg border border-stone-300 px-3 py-2 bg-white">
                    @foreach (User::INTERVALS as $value => $text)
                        <option value="{{ $value }}" @selected((int) old('slot_interval', $user->slot_interval) === $value)>{{ $text }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="何日先まで予約できるか" name="booking_days_ahead">
                <div class="flex items-center gap-2"><input type="number" name="booking_days_ahead" value="{{ old('booking_days_ahead', $user->booking_days_ahead) }}" min="1" max="180" class="w-24 rounded-lg border border-stone-300 px-3 py-2"><span class="text-sm">日先まで</span></div>
            </x-field>
            <x-field label="何時間前まで予約できるか" name="min_notice_hours" hint="0にすると、空いていれば直前でも予約できます">
                <div class="flex items-center gap-2"><input type="number" name="min_notice_hours" value="{{ old('min_notice_hours', $user->min_notice_hours) }}" min="0" max="72" class="w-24 rounded-lg border border-stone-300 px-3 py-2"><span class="text-sm">時間前まで</span></div>
            </x-field>
            <x-field label="お客さんがキャンセルできる締め切り" name="cancel_deadline_hours" hint="これより後は「お店に電話してください」と出ます">
                <div class="flex items-center gap-2"><input type="number" name="cancel_deadline_hours" value="{{ old('cancel_deadline_hours', $user->cancel_deadline_hours) }}" min="0" max="168" class="w-24 rounded-lg border border-stone-300 px-3 py-2"><span class="text-sm">時間前まで</span></div>
            </x-field>
        </div>
    </section>

    <section class="bg-white rounded-xl border border-stone-200 p-6 space-y-4">
        <h2 class="font-bold">ログイン情報</h2>
        @if (config('demo.enabled'))
            <p class="text-sm text-stone-500">デモ版では、メールアドレスとパスワードは変えられません。</p>
        @endif
        <x-field label="メールアドレス" name="email">
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required @readonly(config('demo.enabled')) class="w-full rounded-lg border border-stone-300 px-3 py-2 {{ config('demo.enabled') ? 'bg-stone-50' : '' }}">
        </x-field>
        @unless (config('demo.enabled'))
            <div class="grid sm:grid-cols-3 gap-4">
                <x-field label="現在のパスワード" name="current_password">
                    <input type="password" name="current_password" autocomplete="current-password" class="w-full rounded-lg border border-stone-300 px-3 py-2">
                </x-field>
                <x-field label="新しいパスワード" name="password">
                    <input type="password" name="password" autocomplete="new-password" class="w-full rounded-lg border border-stone-300 px-3 py-2">
                </x-field>
                <x-field label="新しいパスワード(確認)" name="password_confirmation">
                    <input type="password" name="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border border-stone-300 px-3 py-2">
                </x-field>
            </div>
            <p class="text-xs text-stone-500">パスワードを変えないときは空のままで大丈夫です。</p>
        @endunless
    </section>

    <div class="flex flex-wrap gap-2">
        <button class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-6 py-2.5">保存する</button>
        <a href="{{ route('dashboard') }}" data-discard class="rounded-lg border border-stone-300 bg-white px-5 py-2.5">キャンセル</a>
    </div>
</form>
@endsection
