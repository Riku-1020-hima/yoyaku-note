<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** メニュー(カット60分など) */
class Menu extends Model
{
    protected $fillable = ['name', 'duration_minutes', 'price', 'description', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['duration_minutes' => 'integer', 'price' => 'integer', 'sort' => 'integer', 'is_active' => 'boolean'];
    }

    /** 並び順(sort → 作った順) */
    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('menus.sort')->orderBy('menus.id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** このメニューができる担当者 */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'menu_staff');
    }

    /** 「1時間30分」のような所要時間の表示 */
    public function getDurationLabelAttribute(): string
    {
        return self::durationLabel($this->duration_minutes);
    }

    public static function durationLabel(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return ($h ? $h.'時間' : '').($m ? $m.'分' : '');
    }

    public static function yen(int $value): string
    {
        return '¥'.number_format($value);
    }
}
