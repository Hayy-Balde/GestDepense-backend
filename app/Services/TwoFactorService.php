<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use OTPHP\TOTP;

/**
 * Vérification du second facteur (TOTP).
 *
 * Le secret TOTP et les codes de récupération vivent sur l'utilisateur. Aucun
 * secret ne doit être renvoyé par l'API : la méthode ne fait que valider et,
 * en cas d'échec, journalise l'événement.
 */
class TwoFactorService
{
    /**
     * Nombre de périodes de 30 s tolérées de part et d'autre de l'heure courante.
     * 1 période = ~30 s de dérive d'horloge entre le client et le serveur.
     */
    private const WINDOW = 1;

    public function isEnabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null
            && $user->two_factor_secret !== null;
    }

    /**
     * @return array{ok: true, used_recovery_code: ?string}|array{ok: false}
     */
    public function verify(User $user, string $code): array
    {
        if (! $this->isEnabled($user)) {
            return ['ok' => false];
        }

        // 1) Code TOTP à 6 chiffres.
        if (preg_match('/^\d{6}$/', $code) === 1) {
            $otp = TOTP::create($user->two_factor_secret);

            if ($otp->verify($code, null, self::WINDOW)) {
                return ['ok' => true, 'used_recovery_code' => null];
            }
        }

        // 2) Code de récupération (8 caractères hexadécimaux en majuscules).
        $recoveryCodes = $this->recoveryCodes($user);

        if ($recoveryCodes !== []) {
            $needle = strtoupper(trim($code));

            if (in_array($needle, $recoveryCodes, true)) {
                // Un code de récupération est à usage unique.
                $this->consumeRecoveryCode($user, $needle);

                return ['ok' => true, 'used_recovery_code' => $needle];
            }
        }

        Log::warning('Tentative de validation 2FA échouée.', [
            'user_id' => $user->id,
            'ip' => request()->ip(),
        ]);

        return ['ok' => false];
    }

    /**
     * @return list<string>
     */
    public function recoveryCodes(User $user): array
    {
        $raw = $user->two_factor_recovery_codes;

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }

    private function consumeRecoveryCode(User $user, string $used): void
    {
        $remaining = array_values(array_filter(
            $this->recoveryCodes($user),
            static fn (string $code): bool => $code !== $used,
        ));

        $user->forceFill([
            'two_factor_recovery_codes' => json_encode($remaining),
        ])->save();
    }
}
