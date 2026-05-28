<?php

use App\Modules\Booking\Http\Controllers\ReservationController;
use App\Modules\Driver\Http\Controllers\DriverApplicationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReservationController::class, 'index'])->name('home');

Route::post('/rezervasyon', [ReservationController::class, 'store'])
    ->name('reservation.store');

Route::get('/rezervasyon/{publicId}', [ReservationController::class, 'confirmation'])
    ->name('reservation.confirmation');

// AJAX: canlı fiyat hesabı
Route::post('/api/calculate-fare', [ReservationController::class, 'calculateFare'])
    ->name('reservation.calculate-fare');

// AJAX: Canlı radar - hızlı sürücü talebi
Route::post('/api/quick-request', [ReservationController::class, 'quickRequest'])
    ->name('reservation.quick-request');

// Sürücü başvuru sayfası
Route::get('/surucu-olun', [DriverApplicationController::class, 'show'])
    ->name('driver.apply');
Route::post('/surucu-olun', [DriverApplicationController::class, 'store'])
    ->name('driver.apply.store');

// Yolculuk Yapın - yolcu landing
Route::view('/yolculuk-yapin', 'ride.show')->name('ride.show');
