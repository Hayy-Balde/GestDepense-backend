<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (file_exists(public_path('index.html'))) {
        return response()->file(public_path('index.html'));
    }
    return view('welcome');
});

// Health check
//
// Volontairement identifié par sa route Laravel interne `/up` plutôt que par
// une route applicative : l'ancienne route `/testbd` exposait publiquement l'état
// de la base (et son nom révélait un test de diagnostic). Elle n'est plus
// accessible en production.
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('health');

// SPA fallback (actif seulement si le build frontend est présent dans public/)
if (file_exists(public_path('index.html'))) {
    Route::get('/{view}', function () {
        return response()->file(public_path('index.html'));
    })->where('view', '^(?!api/).*');
}
