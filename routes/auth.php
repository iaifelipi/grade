<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\TenantAuthenticatedSessionController;
use App\Http\Controllers\Auth\TenantRegisteredUserController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TENANT AUTH (default public auth)
|--------------------------------------------------------------------------
*/
Route::middleware('guest:tenant')->group(function () {
    Route::get('register', fn () => redirect()->route('home'))
        ->name('register');

    Route::post('register', [TenantRegisteredUserController::class, 'store'])
        ->name('register.store');

    Route::get('login', fn () => redirect()->route('home'))
        ->name('login');

    Route::post('login', [TenantAuthenticatedSessionController::class, 'store'])
        ->name('login.store');

    // Legacy compatibility: old tenant login endpoints.
    Route::get('tenant/login', fn () => redirect()->route('login', status: 301))
        ->name('tenant.login');

    Route::post('tenant/login', [TenantAuthenticatedSessionController::class, 'store'])
        ->name('tenant.login.store');
});

/*
|--------------------------------------------------------------------------
| SYSTEM AUTH (web/users) under /admin/*
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->prefix('admin')->name('admin.')->group(function () {
    Route::get('register', fn () => redirect()->route('admin.login', status: 301))
        ->name('register');

    Route::post('register', fn () => abort(403, 'Auto cadastro de admin desativado.'))
        ->name('register.store');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->name('login.store');

});

// Web password reset kept on legacy root paths for compatibility.
Route::middleware('guest')->group(function () {
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
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

Route::middleware('auth:tenant')->group(function () {
    Route::post('tenant/logout', [TenantAuthenticatedSessionController::class, 'destroy'])
        ->name('tenant.logout');
});
