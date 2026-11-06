<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // お店の情報と、予約の受け付けルール
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug', 40)->nullable()->unique()->after('name');        // 予約ページのURL(/r/〇〇)
            $table->string('phone', 30)->nullable()->after('slug');
            $table->string('address')->nullable()->after('phone');
            $table->text('notice')->nullable()->after('address');                    // 予約ページに出すお知らせ
            $table->json('business_hours')->nullable()->after('notice');             // 曜日ごとの営業時間 {"0":null,"1":["10:00","19:00"],…}
            $table->unsignedSmallInteger('slot_interval')->default(30)->after('business_hours');       // 予約できる時刻の間隔(分)
            $table->unsignedSmallInteger('booking_days_ahead')->default(30)->after('slot_interval');   // 何日先まで予約できるか
            $table->unsignedSmallInteger('min_notice_hours')->default(2)->after('booking_days_ahead'); // 何時間前まで予約できるか
            $table->unsignedSmallInteger('cancel_deadline_hours')->default(24)->after('min_notice_hours'); // 何時間前までキャンセルできるか
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'phone', 'address', 'notice', 'business_hours', 'slot_interval', 'booking_days_ahead', 'min_notice_hours', 'cancel_deadline_hours']);
        });
    }
};
