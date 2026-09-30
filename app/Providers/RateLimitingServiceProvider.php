<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Définition des quotas de l'API.
 *
 * Aucune de ces routes n'était limitée avant : le nombre de tentatives de
 * connexion était infini, ce qui rendait le bourrage de mot de passe et
 * l'énumération de comptes purement triviaux.
 */
class RateLimitingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Authentification : borne agressive, clé par IP + adresse saisie, pour
        // contrer à la fois le balayage d'adresses et le bourrage d'un compte
        // précis depuis une IP unique.
        RateLimiter::for('auth', function (Request $request) {
            $key = Str::transliterate(
                Str::lower((string) $request->input('email')).'|'.$request->ip()
            );

            return Limit::perMinute(5)->by($key);
        });

        // Quota global de l'API. Un utilisateur authentifié est plafonné
        // individuellement : le trafic d'un autre ne peut pas le bloquer.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($this->throttleKey($request));
        });

        // Opérations sensibles (mot de passe, 2FA, suppression de compte) :
        // quelques tentatives par minute suffisent largement.
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(10)->by($this->throttleKey($request));
        });

        // Écritures : limite plus basse, les lectures restent généreuses.
        RateLimiter::for('writes', function (Request $request) {
            return Limit::perMinute(60)->by($this->throttleKey($request));
        });
    }

    private function throttleKey(Request $request): string
    {
        /** @var User|null $user */
        $user = $request->user();

        return $user?->id !== null
            ? 'user:'.$user->id
            : 'ip:'.$request->ip();
    }
}
