<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** お休み(staff_id が空ならお店ごと休み) */
class DayOff extends Model
{
    protected $fillable = ['staff_id', 'date', 'note'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
