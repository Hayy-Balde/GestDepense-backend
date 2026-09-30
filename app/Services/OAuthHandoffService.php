<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Passerelle entre le callback OAuth et le frontend.
 *
 * Le callback fournisseur atterrit côté API ; le frontend doit ensuite obtenir le
 * jeton d'accès. Placer ce jeton dans l'URL de redirection (`?token=...`)
 * l'exposait dans l'historique du navigateur, dans le `Referer` des requêtes
 * suivantes et dans les logs du CDN.
 *
 * On émet donc un code opaque à usage unique, échangé contre le jeton via un
 * appel POST : l'URL transportée par le navigateur ne vaut rien si elle est
 * interceptée, et le code ne peut servir qu'une fois.
 */
class OAuthHandoffService
{
    private const TTL_SECONDS = 300;

    private const CACHE_PREFIX = 'oauth_handoff:';

    public function issue(User $user): string
    {
        // 32 octets aléatoires : l'espace de recherche est hors de portée.
        $code = Str::random(64);

        Cache::put(self::CACHE_PREFIX.$this->key($code), [
            'user_id' => $user->id,
        ], self::TTL_SECONDS);

        return $code;
    }

    /**
     * Échange le code contre l'utilisateur, en le consommant au passage.
     */
    public function redeem(string $code): ?User
    {
        $cacheKey = self::CACHE_PREFIX.$this->key($code);

        // `pull` est atomique : deux appels concurrents ne peuvent pas
        // récupérer la même entrée, donc un code ne sert qu'une seule fois.
        $entry = Cache::pull($cacheKey);

        if (! is_array($entry) || ! isset($entry['user_id'])) {
            return null;
        }

        return User::find($entry['user_id']);
    }

    private function key(string $code): string
    {
        return hash('sha256', $code);
    }
}
