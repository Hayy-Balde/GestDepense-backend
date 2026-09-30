<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Jeton de challenge 2FA.
 *
 * Après un login réussi sans second facteur, l'API renvoie un challenge opaque
 * au lieu d'un jeton d'accès. Le challenge est chiffré (AES-256-GCM) et porte une
 * expiration : il ne donne aucun accès aux données, ne peut pas être rejoué et
 * disparaît seul. Aucun état serveur n'est nécessaire, ce qui évite un nouveau
 * stockage à nettoyer.
 */
class TwoFactorChallengeService
{
    /** Durée de validité du challenge : le temps de saisir un code. */
    private const TTL_SECONDS = 300;

    /**
     * Marque les challenges déjà consommés.
     *
     * Le challenge doit être à usage unique : sinon, quiconque l'intercepte
     * peut réessayer des codes pendant toute la fenêtre de validité. Le
     * compteur canonique est déjà limité par `throttle:sensitive`, mais la
     * garantie « un challenge, une tentative » doit reposer sur le challenge
     * lui-même, pas sur le quota.
     */
    private const CONSUMED_PREFIX = 'two_factor_challenge_used:';

    public function issue(User $user): string
    {
        $payload = [
            'uid' => $user->id,
            'iat' => time(),
            'exp' => time() + self::TTL_SECONDS,
            'nbf' => time(),
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Renvoie l'utilisateur associé au challenge, ou null si le challenge est
     * absent, altéré, expiré, émis dans le futur ou déjà utilisé.
     */
    public function resolve(string $challenge): ?User
    {
        try {
            $decoded = json_decode(Crypt::decryptString($challenge), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (! is_array($decoded) || ! isset($decoded['uid'], $decoded['exp'], $decoded['nbf'])) {
            return null;
        }

        $now = time();

        if ($now < (int) $decoded['nbf'] || $now > (int) $decoded['exp']) {
            return null;
        }

        // `add` (et non `put`) n'inscrit la clé que si elle n'existe pas déjà :
        // deux requêtes concurrentes sur le même challenge ne peuvent donc pas
        // passer toutes les deux.
        if (! Cache::add($this->consumedKey($challenge), true, self::TTL_SECONDS)) {
            return null;
        }

        return User::find($decoded['uid']);
    }

    private function consumedKey(string $challenge): string
    {
        return self::CONSUMED_PREFIX.hash('sha256', $challenge);
    }
}
