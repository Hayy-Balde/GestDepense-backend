<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
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