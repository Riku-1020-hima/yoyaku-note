<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** 予約 */
class Reservation extends Model
{
    public const STATUSES = ['confirmed' => '予約', 'visited' => '来店済み', 'no_show' => '無断キャンセル', 'cancelled' => 'キャンセル'];

    /** 時間をふさぐ状態(キャンセル以外) */
    public const BLOCKING = ['confirmed', 'visited', 'no_show'];

    public const SOURCES = ['web' => '予約ページ', 'shop' => 'お店で入力'];

    protected $fillable = [
        'staff_id', 'menu_id', 'menu_name', 'price', 'duration_minutes', 'starts_at', 'ends_at',
        'customer_name', 'customer_phone', 'customer_note', 'shop_note', 'status', 'source', 'nominated',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'price' => 'integer',
            'duration_minutes' => 'integer',
            'nominated' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Reservation $r) {
            $r->token ??= Str::random(32);
        });
        static::saving(function (Reservation $r) {
            $r->phone_digits = self::digits($r->customer_phone);
        });
    }

    /** 電話番号を数字だけにする(全角・ハイフンありでも同じ人として探せるように) */
    public static function digits(?string $phone): string
    {
        return preg_replace('/\D/', '', mb_convert_kana((string) $phone, 'n'));
    }

    public function scopeBlocking(Builder $q): Builder
    {
        return $q->whereIn('status', self::BLOCKING);
    }

    /** [from, to) と時間が重なる予約 */
    public function scopeOverlapping(Builder $q, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $q->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** 「11/8(土) 10:00〜11:00」 */
    public function getWhenLabelAttribute(): string
    {
        return self::dayLabel($this->starts_at).' '.$this->starts_at->format('G:i').'〜'.$this->ends_at->format('G:i');
    }

    public static function dayLabel(CarbonImmutable $d): string
    {
        return $d->format('n/j').'('.User::WEEKDAYS[$d->dayOfWeek].')';
    }

    /** お客さん用の確認・キャンセルページ */
    public function getManageUrlAttribute(): string
    {
        return route('booking.show', $this->token);
    }

    /** お客さんがこのページからキャンセルできる締め切り */
    public function cancelDeadline(User $shop): CarbonImmutable
    {
        return $this->starts_at->subHours($shop->cancel_deadline_hours);
    }

    public function canCancelOnline(User $shop): bool
    {
        return $this->status === 'confirmed' && now()->lte($this->cancelDeadline($shop));
    }
}
