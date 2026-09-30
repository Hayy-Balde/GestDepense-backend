<?php

use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;


// Social Auth (without prefix — full URL needed for OAuth redirects)
Route::get('auth/{provider}/redirect', 'App\Http\Controllers\Api\V1\SocialAuthController@redirect');
Route::get('auth/{provider}/callback', 'App\Http\Controllers\Api\V1\SocialAuthController@callback');
// Échange du code de passerelle contre un jeton : en POST, pour que le code
// n'apparaisse dans aucun journal d'accès.
Route::post('auth/oauth/exchange', 'App\Http\Controllers\Api\V1\SocialAuthController@exchange')
    ->middleware('throttle:sensitive');

// Auth Routes
Route::prefix('v1/auth')->group(function () {
    Route::post('register', 'App\Http\Controllers\Api\V1\AuthController@register')->middleware('throttle:auth');
    Route::post('login', 'App\Http\Controllers\Api\V1\AuthController@login')->middleware('throttle:auth');
    Route::post('login/2fa', 'App\Http\Controllers\Api\V1\AuthController@verifyTwoFactorLogin')->middleware('throttle:sensitive');
    Route::post('forgot-password', 'App\Http\Controllers\Api\V1\AuthController@forgotPassword')->middleware('throttle:auth');
    Route::post('reset-password', 'App\Http\Controllers\Api\V1\AuthController@resetPassword')->middleware('throttle:sensitive');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', 'App\Http\Controllers\Api\V1\AuthController@logout');
        Route::get('user', 'App\Http\Controllers\Api\V1\AuthController@user');
        Route::put('user', 'App\Http\Controllers\Api\V1\AuthController@updateProfile');
        Route::post('verify-password', 'App\Http\Controllers\Api\V1\AuthController@verifyPassword');

        // Settings
        Route::get('profile', [SettingsController::class, 'profile']);
        Route::put('profile', [SettingsController::class, 'updateProfile']);
        Route::put('preferences', [SettingsController::class, 'updatePreferences']);
        Route::put('password', [SettingsController::class, 'updatePassword'])->middleware('throttle:sensitive');
        Route::delete('account', [SettingsController::class, 'deleteAccount'])->middleware('throttle:sensitive');

        // Sessions
        Route::get('sessions', [SettingsController::class, 'sessions']);
        Route::delete('sessions/{id}', [SettingsController::class, 'revokeSession']);

        // Avatar
        Route::post('avatar', [SettingsController::class, 'updateAvatar']);

        // 2FA
        Route::get('2fa', [SettingsController::class, 'twoFactorStatus']);
        Route::post('2fa/enable', [SettingsController::class, 'enable2fa'])->middleware('throttle:sensitive');
        Route::post('2fa/verify', [SettingsController::class, 'verify2fa'])->middleware('throttle:sensitive');
        Route::post('2fa/disable', [SettingsController::class, 'disable2fa'])->middleware('throttle:sensitive');
    });
});

// Protected API Routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Accounts
    Route::apiResource('accounts', 'App\Http\Controllers\Api\V1\AccountController');

    // Expenses
    Route::get('expenses/stats', 'App\Http\Controllers\Api\V1\ExpenseStatsController@index');
    Route::get('expenses/export', 'App\Http\Controllers\Api\V1\ExpenseController@export');
    Route::apiResource('expenses', 'App\Http\Controllers\Api\V1\ExpenseController');

    // Incomes
    Route::get('incomes/stats', 'App\Http\Controllers\Api\V1\IncomeStatsController@index');
    Route::apiResource('incomes', 'App\Http\Controllers\Api\V1\IncomeController');

    // Quick Entries (saisie rapide / ajout massif)
    Route::post('quick-entries', 'App\Http\Controllers\Api\V1\QuickEntryController@store');

    // Categories
    Route::apiResource('categories', 'App\Http\Controllers\Api\V1\CategoryController')->only(['index', 'store', 'update', 'destroy']);

    // App Config / Config tables
    Route::get('config', 'App\Http\Controllers\Api\V1\ConfigController@index');
    Route::resource('config/account-types', 'App\Http\Controllers\Api\V1\AccountTypeController')->parameters(['account-types' => 'value']);
    Route::resource('config/payment-methods', 'App\Http\Controllers\Api\V1\PaymentMethodController')->parameters(['payment-methods' => 'value']);
    Route::resource('config/billing-cycles', 'App\Http\Controllers\Api\V1\BillingCycleController')->parameters(['billing-cycles' => 'value']);
    Route::resource('config/debt-types', 'App\Http\Controllers\Api\V1\DebtTypeController')->parameters(['debt-types' => 'value']);
    Route::resource('config/currencies', 'App\Http\Controllers\Api\V1\CurrencyController')->parameters(['currencies' => 'value']);

    // Caisses
    Route::get('caisses/{id}/stats', 'App\Http\Controllers\Api\V1\CaisseController@stats');
    Route::post('caisses/{id}/fund', 'App\Http\Controllers\Api\V1\CaisseController@fund');
    Route::post('caisses/{id}/close', 'App\Http\Controllers\Api\V1\CaisseController@close');
    Route::post('caisses/{id}/transfer', 'App\Http\Controllers\Api\V1\CaisseController@transfer');
    Route::apiResource('caisses', 'App\Http\Controllers\Api\V1\CaisseController')->only(['index', 'store', 'show', 'update', 'destroy']);

    // Movements (history of all money movements)
    Route::get('movements/stats', 'App\Http\Controllers\Api\V1\MovementController@stats');
    Route::get('movements', 'App\Http\Controllers\Api\V1\MovementController@index');

    // Account money operations
    Route::post('accounts/{id}/apport', 'App\Http\Controllers\Api\V1\AccountController@apport');
    Route::post('accounts/{id}/regulate', 'App\Http\Controllers\Api\V1\AccountController@regulate');
    Route::post('accounts/{id}/transfer', 'App\Http\Controllers\Api\V1\AccountController@transfer');

    // Reports
    Route::get('reports/summary', 'App\Http\Controllers\Api\V1\ReportController@summary');

    // Savings
    Route::post('savings/{id}/deposit', 'App\Http\Controllers\Api\V1\SavingController@deposit');
    Route::post('savings/{id}/withdraw', 'App\Http\Controllers\Api\V1\SavingController@withdraw');
    Route::apiResource('savings', 'App\Http\Controllers\Api\V1\SavingController')->only(['index', 'store', 'update', 'destroy']);

    // Budgets
    Route::get('budgets/by-category', 'App\Http\Controllers\Api\V1\BudgetController@byCategory');
    Route::get('budgets/{month}/{year}', 'App\Http\Controllers\Api\V1\BudgetController@getByMonth');
    Route::apiResource('budgets', 'App\Http\Controllers\Api\V1\BudgetController')->only(['index', 'store']);

    // Subscriptions
    Route::post('subscriptions/{id}/pay', 'App\Http\Controllers\Api\V1\SubscriptionController@pay');
    Route::put('subscriptions/{id}/toggle', 'App\Http\Controllers\Api\V1\SubscriptionController@toggle');
    Route::apiResource('subscriptions', 'App\Http\Controllers\Api\V1\SubscriptionController')->only(['index', 'store', 'update', 'destroy']);

    // Debts
    Route::post('debts/{id}/payments', 'App\Http\Controllers\Api\V1\DebtController@payment');
    Route::apiResource('debts', 'App\Http\Controllers\Api\V1\DebtController')->only(['index', 'store', 'update', 'destroy']);

    // Invoices (factures)
    Route::post('invoices/{id}/payments', 'App\Http\Controllers\Api\V1\InvoiceController@payment');
    Route::apiResource('invoices', 'App\Http\Controllers\Api\V1\InvoiceController')->only(['index', 'store', 'update', 'destroy']);

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/', 'App\Http\Controllers\Api\V1\DashboardController@index');
        Route::get('monthly-summary', 'App\Http\Controllers\Api\V1\DashboardController@monthlySummary');
        Route::get('trends', 'App\Http\Controllers\Api\V1\DashboardController@trends');
    });

    // Analytics
    Route::prefix('analytics')->group(function () {
        Route::get('trends', 'App\Http\Controllers\Api\V1\AnalyticsController@trends');
        Route::get('breakdown', 'App\Http\Controllers\Api\V1\AnalyticsController@breakdown');
        Route::get('monthly', 'App\Http\Controllers\Api\V1\AnalyticsController@monthly');
    });

    // Notifications
    Route::get('notifications', 'App\Http\Controllers\Api\V1\NotificationController@index');
    Route::put('notifications/{id}/read', 'App\Http\Controllers\Api\V1\NotificationController@read');

    // Recherche globale
    Route::get('search', 'App\Http\Controllers\Api\V1\SearchController@search');
});
