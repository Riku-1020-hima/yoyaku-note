<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/** お店(アカウント) */
class User extends Authenticatable
{
    use Notifiable;

    public const DEFAULT_APP_NAME = 'ヨヤクノート';

    public const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    /** はじめの営業時間(月曜定休・10時〜19時) */
    public const DEFAULT_HOURS = ['0' => ['10:00', '19:00'], '1' => null, '2' => ['10:00', '19:00'], '3' => ['10:00', '19:00'],
        '4' => ['10:00', '19:00'], '5' => ['10:00', '19:00'], '6' => ['10:00', '19:00']];

    public const INTERVALS = [15 => '15分ごと', 30 => '30分ごと', 60 => '1時間ごと'];

    protected $fillable = [
        'name', 'slug', 'phone', 'address', 'notice', 'business_hours', 'slot_interval',
        'booking_days_ahead', 'min_notice_hours', 'cancel_deadline_hours', 'email', 'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * 受け付けルールのはじめの値
     * (データベースの初期値と同じ。作った直後の、まだ読み直していないお店でも同じ値になるように)
     */
    protected $attributes = [
        'slot_interval' => 30,
        'booking_days_ahead' => 30,
        'min_notice_hours' => 2,
        'cancel_deadline_hours' => 24,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'business_hours' => 'array',
            'slot_interval' => 'integer',
            'booking_days_ahead' => 'integer',
            'min_notice_hours' => 'integer',
            'cancel_deadline_hours' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->slug ??= self::newSlug();
            $user->business_hours ??= self::DEFAULT_HOURS;
        });
    }

    public static function newSlug(): string
    {
        do {
            $slug = Str::lower(Str::random(10));
        } while (self::where('slug', $slug)->exists());

        return $slug;
    }

    public function getDisplayAppNameAttribute(): string
    {
        return self::DEFAULT_APP_NAME;
    }

    /** その曜日の営業時間 ['10:00', '19:00'](休みなら null) */
    public function hoursFor(int $weekday): ?array
    {
        $hours = ($this->business_hours ?? self::DEFAULT_HOURS)[(string) $weekday] ?? null;

        return is_array($hours) && count($hours) === 2 && $hours[0] < $hours[1] ? $hours : null;
    }

    /** 営業している曜日 [0,2,3…] */
    public function openWeekdays(): array
    {
        return array_values(array_filter(range(0, 6), fn ($w) => $this->hoursFor($w) !== null));
    }

    /** お客さんの予約ページ */
    public function getBookingUrlAttribute(): string
    {
        return route('booking.shop', $this->slug);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function dayOffs(): HasMany
    {
        return $this->hasMany(DayOff::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
