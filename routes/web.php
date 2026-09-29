<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (file_exists(public_path('index.html'))) {
        return response()->file(public_path('index.html'));
    }
    return view('welcome');
});

// Health check
Route::get('/testbd', function () {
    $db = 'disconnected';
    try {
        DB::connection()->getPdo();
        $db = 'connected';
    } catch (\Throwable) {
        $db = 'disconnected';
    }
    return response()->json([
        'status' => 'ok',
        'database' => $db,
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('testbd');

// SPA fallback (actif seulement si le build frontend est présent dans public/)
if (file_exists(public_path('index.html'))) {
    Route::get('/{view}', function () {
        return response()->file(public_path('index.html'));
    })->where('view', '^(?!api/).*');
}