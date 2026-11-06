<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // 公開デモでは、みんなが同じアカウントを使うのでログイン情報と予約ページのURLは変えられない
        $demo = config('demo.enabled');
        if ($demo) {
            $request->merge(['email' => $user->email, 'slug' => $user->slug]);
            foreach (['password', 'password_confirmation', 'current_password'] as $key) {
                $request->request->remove($key);
            }
        }
        $request->merge(['slug' => strtolower(trim((string) $request->input('slug')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'regex:/^[a-z0-9][a-z0-9-]{2,39}$/', Rule::unique('users', 'slug')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:200'],
            'notice' => ['nullable', 'string', 'max:1000'],
            'hours' => ['array'],
            'hours.*.open' => ['nullable', 'boolean'],
            'hours.*.from' => ['nullable', 'date_format:H:i'],
            'hours.*.to' => ['nullable', 'date_format:H:i'],
            'slot_interval' => ['required', Rule::in(array_keys(User::INTERVALS))],
            'booking_days_ahead' => ['required', 'integer', 'min:1', 'max:180'],
            'min_notice_hours' => ['required', 'integer', 'min:0', 'max:72'],
            'cancel_deadline_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], [
            'slug.regex' => '予約ページのURLは、半角の小文字・数字・ハイフン(-)で3〜40文字にしてください。',
            'slug.unique' => 'この予約ページのURLは、ほかのお店が使っています。',
            'current_password.current_password' => '現在のパスワードが違います。',
            'current_password.required_with' => 'パスワードを変えるときは、現在のパスワードも入力してください。',
        ], [
            'name' => 'お店の名前', 'slug' => '予約ページのURL', 'phone' => '電話番号', 'address' => '住所', 'notice' => 'お知らせ',
            'booking_days_ahead' => '何日先まで', 'min_notice_hours' => '何時間前まで', 'cancel_deadline_hours' => 'キャンセルの締め切り',
        ]);

        $user->fill(collect($data)->except(['current_password', 'password', 'hours'])->all());
        if ($request->has('hours')) {
            $user->business_hours = $this->hours($request->input('hours', []));
        }
        if (! $demo && ! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return redirect()->route('settings.edit')->with('status', '設定を保存しました。');
    }

    /** 曜日ごとの「営業する・開店・閉店」を {"0":["10:00","19:00"],"1":null,…} にする */
    private function hours(array $input): array
    {
        $hours = [];
        $errors = [];
        foreach (range(0, 6) as $w) {
            $row = $input[$w] ?? [];
            if (empty($row['open'])) {
                $hours[(string) $w] = null;

                continue;
            }
            $from = $row['from'] ?? null;
            $to = $row['to'] ?? null;
            if (! $from || ! $to || $from >= $to) {
                $errors["hours.$w.to"] = User::WEEKDAYS[$w].'曜日の閉店時間は、開店時間より後にしてください。';
            }
            $hours[(string) $w] = [$from, $to];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $hours;
    }
}
