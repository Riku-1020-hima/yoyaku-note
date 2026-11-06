<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // メニュー(カット60分など)
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->unsignedSmallInteger('duration_minutes');  // 所要時間(分)
            $table->unsignedInteger('price')->default(0);      // 料金(税込)
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 担当者(スタイリスト・施術者)
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('title', 30)->nullable();      // 肩書(店長・スタイリストなど)
            $table->json('work_weekdays')->nullable();     // 出勤する曜日 [1,2,3…](0=日曜)
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // どの担当者がどのメニューをできるか
        Schema::create('menu_staff', function (Blueprint $table) {
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->primary(['menu_id', 'staff_id']);
        });

        // お休み(staff_id が空ならお店ごと休み)
        Schema::create('day_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->cascadeOnDelete();
            $table->date('date');
            $table->string('note', 100)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_offs');
        Schema::dropIfExists('menu_staff');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('menus');
    }
};
