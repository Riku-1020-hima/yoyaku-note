<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('menu_id')->nullable()->constrained()->nullOnDelete();
            // 予約したときのメニューの内容を写しておく(あとでメニューを変えても、予約の中身は変わらない)
            $table->string('menu_name', 50);
            $table->unsignedInteger('price')->default(0);
            $table->unsignedSmallInteger('duration_minutes');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('customer_name', 50);
            $table->string('customer_phone', 30);
            $table->string('phone_digits', 20);            // 数字だけの電話番号(同じお客さんを探すため)
            $table->text('customer_note')->nullable();      // お客さんからの要望
            $table->text('shop_note')->nullable();          // お店のメモ(お客さんには見えない)
            $table->string('status', 20)->default('confirmed'); // confirmed / visited / no_show / cancelled
            $table->string('source', 10)->default('web');   // web(予約ページ)/ shop(お店で入力)
            $table->boolean('nominated')->default(false);   // 担当者の指名あり
            $table->string('token', 40)->unique();          // お客さん用の確認・キャンセルページ
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'starts_at']);
            $table->index(['user_id', 'starts_at']);
            $table->index(['user_id', 'phone_digits']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
