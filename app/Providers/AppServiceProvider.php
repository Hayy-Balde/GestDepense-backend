<?php

namespace App\Providers;

use App\Repositories\ExpenseRepository;
use App\Repositories\Interfaces\ExpenseRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ExpenseRepositoryInterface::class, ExpenseRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Pour les JsonResource
        JsonResource::withoutWrapping();
        
        // Optionnel : Forcer l'encodage JSON par défaut
        JsonResponse::macro('customEncoding', function ($data) {
            return response()->json($data, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        });
    }
}
