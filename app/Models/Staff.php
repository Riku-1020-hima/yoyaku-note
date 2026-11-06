<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** 担当者(スタイリスト・施術者) */
class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = ['name', 'title', 'work_weekdays', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['work_weekdays' => 'array', 'sort' => 'integer', 'is_active' => 'boolean'];
    }

    /** 並び順(sort → 作った順) */
    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('staff.sort')->orderBy('staff.id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** その曜日に出勤するか */
    public function worksOn(int $weekday): bool
    {
        return in_array($weekday, array_map('intval', $this->work_weekdays ?? []), true);
    }

    public function getWorkDaysLabelAttribute(): string
    {
        $days = array_map('intval', $this->work_weekdays ?? []);
        sort($days);

        return $days ? implode('・', array_map(fn ($d) => User::WEEKDAYS[$d], $days)) : '出勤日なし';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'menu_staff');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
