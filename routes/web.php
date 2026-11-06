<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DayOffController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

// お客さん用の予約ページ(ログイン不要)
Route::prefix('r/{slug}')->group(function () {
    Route::get('/', [BookingController::class, 'shop'])->name('booking.shop');
    Route::get('/menus/{menu}', [BookingController::class, 'times'])->name('booking.times')->whereNumber('menu');
    Route::get('/menus/{menu}/form', [BookingController::class, 'form'])->name('booking.form')->whereNumber('menu');
    Route::post('/menus/{menu}', [BookingController::class, 'store'])->name('booking.store')->whereNumber('menu')->middleware('throttle:10,1');
});
// 予約したお客さん用の確認・キャンセルページ(予約ごとの専用URL)
Route::get('/b/{token}', [BookingController::class, 'show'])->name('booking.show');
Route::get('/b/{token}/calendar.ics', [BookingController::class, 'ics'])->name('booking.ics');
Route::post('/b/{token}/cancel', [BookingController::class, 'cancel'])->name('booking.cancel')->middleware('throttle:10,1');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::post('/reservations/{reservation}/status', [ReservationController::class, 'status'])->name('reservations.status');
    Route::resource('reservations', ReservationController::class)->except('destroy');

    Route::resource('menus', MenuController::class)->except('show');
    Route::resource('staff', StaffController::class)->except('show')->parameters(['staff' => 'member']);

    Route::get('/day-offs', [DayOffController::class, 'index'])->name('day-offs.index');
    Route::post('/day-offs', [DayOffController::class, 'store'])->name('day-offs.store');
    Route::delete('/day-offs/{dayOff}', [DayOffController::class, 'destroy'])->name('day-offs.destroy');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{phone}', [CustomerController::class, 'show'])->name('customers.show')->where('phone', '[0-9]+');
});
