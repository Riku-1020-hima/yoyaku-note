<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    /** 自分のお店のデータでなければ「見つかりません」にする(他のお店のデータは見えない) */
    protected function own(Model $model): void
    {
        abort_unless((int) $model->user_id === (int) auth()->id(), 404);
    }
}
