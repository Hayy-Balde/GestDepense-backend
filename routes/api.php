<?php

use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;


// Social Auth (without prefix — full URL needed for OAuth redirects)
Route::get('auth/{provider}/redirect', 'App\Http\Controllers\Api\V1\SocialAuthController@redirect');
Route::get('auth/{provider}/callback', 'App\Http\Controllers\Api\V1\SocialAuthController@callback');

// Auth Routes
Route::prefix('v1/auth')->group(function () {
    Route::post('register', 'App\Http\Controllers\Api\V1\AuthController@register');
    Route::post('login', 'App\Http\Controllers\Api\V1\AuthController@login');
    Route::post('forgot-password', 'App\Http\Controllers\Api\V1\AuthController@forgotPassword');
    Route::post('reset-password', 'App\Http\Controllers\Api\V1\AuthController@resetPassword');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', 'App\Http\Controllers\Api\V1\AuthController@logout');
        Route::get('user', 'App\Http\Controllers\Api\V1\AuthController@user');
        Route::put('user', 'App\Http\Controllers\Api\V1\AuthController@updateProfile');

        // Settings
        Route::get('profile', [SettingsController::class, 'profile']);
        Route::put('profile', [SettingsController::class, 'updateProfile']);
        Route::put('preferences', [SettingsController::class, 'updatePreferences']);
        Route::put('password', [SettingsController::class, 'updatePassword']);
        Route::delete('account', [SettingsController::class, 'deleteAccount']);

        // Sessions
        Route::get('sessions', [SettingsController::class, 'sessions']);
        Route::delete('sessions/{id}', [SettingsController::class, 'revokeSession']);

        // Avatar
        Route::post('avatar', [SettingsController::class, 'updateAvatar']);

        // 2FA
        Route::get('2fa', [SettingsController::class, 'twoFactorStatus']);
        Route::post('2fa/enable', [SettingsController::class, 'enable2fa']);
        Route::post('2fa/verify', [SettingsController::class, 'verify2fa']);
        Route::post('2fa/disable', [SettingsController::class, 'disable2fa']);
    });
});

// Protected API Routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Accounts
    Route::apiResource('accounts', 'App\Http\Controllers\Api\V1\AccountController');

    // Expenses
    Route::get('expenses/export', 'App\Http\Controllers\Api\V1\ExpenseController@export');
    Route::apiResource('expenses', 'App\Http\Controllers\Api\V1\ExpenseController');

    // Incomes
    Route::apiResource('incomes', 'App\Http\Controllers\Api\V1\IncomeController');

    // Categories
    Route::apiResource('categories', 'App\Http\Controllers\Api\V1\CategoryController')->only(['index', 'store']);

    // Caisses
    Route::get('caisses/{id}/stats', 'App\Http\Controllers\Api\V1\CaisseController@stats');
    Route::apiResource('caisses', 'App\Http\Controllers\Api\V1\CaisseController')->only(['index', 'store']);

    // Savings
    Route::post('savings/{id}/deposit', 'App\Http\Controllers\Api\V1\SavingController@deposit');
    Route::post('savings/{id}/withdraw', 'App\Http\Controllers\Api\V1\SavingController@withdraw');
    Route::apiResource('savings', 'App\Http\Controllers\Api\V1\SavingController')->only(['index', 'store']);

    // Budgets
    Route::get('budgets/{month}/{year}', 'App\Http\Controllers\Api\V1\BudgetController@getByMonth');
    Route::apiResource('budgets', 'App\Http\Controllers\Api\V1\BudgetController')->only(['index', 'store']);

    // Subscriptions
    Route::apiResource('subscriptions', 'App\Http\Controllers\Api\V1\SubscriptionController')->only(['index', 'store']);

    // Debts
    Route::post('debts/{id}/payment', 'App\Http\Controllers\Api\V1\DebtController@payment');
    Route::apiResource('debts', 'App\Http\Controllers\Api\V1\DebtController')->only(['index', 'store']);

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/', 'App\Http\Controllers\Api\V1\DashboardController@index');
        Route::get('monthly-summary', 'App\Http\Controllers\Api\V1\DashboardController@monthlySummary');
        Route::get('trends', 'App\Http\Controllers\Api\V1\DashboardController@trends');
        Route::get('category-breakdown', 'App\Http\Controllers\Api\V1\DashboardController@categoryBreakdown');
    });

    // Notifications
    Route::get('notifications', 'App\Http\Controllers\Api\V1\NotificationController@index');
    Route::put('notifications/{id}/read', 'App\Http\Controllers\Api\V1\NotificationController@read');
});
