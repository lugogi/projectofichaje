<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('darse-de-alta', [\App\Http\Controllers\Auth\EmployeeApplicationController::class, 'create'])
        ->name('employee-application.create');

    Route::post('darse-de-alta', [\App\Http\Controllers\Auth\EmployeeApplicationController::class, 'store'])
        ->name('employee-application.store');

    Route::post('darse-de-alta/otp/send', [\App\Http\Controllers\Auth\EmployeeApplicationOtpController::class, 'send'])
        ->middleware('throttle:5,1')
        ->name('employee-application.otp.send');

    Route::post('darse-de-alta/otp/verify', [\App\Http\Controllers\Auth\EmployeeApplicationOtpController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('employee-application.otp.verify');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
