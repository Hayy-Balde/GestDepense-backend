<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Journal d'audit.
 *
 * La table `auditlogs` existait mais n'était jamais alimentée : il n'y avait donc
 * aucune trace exploitable en cas d'incident sur les données financières.
 *
 * Points de conception :
 * - l'écriture ne doit jamais faire échouer l'opération métier journalisée
 *   (sinon un incident de journalisation devient une panne de l'application) ;
 * - on ne journalise jamais de secret ni de donnée sensible en clair ;
 * - chaque entrée porte l'IP et l'agent, ce qui est indispensable pour
 *   distinguer l'utilisateur d'un attaquant.
 */
class AuditService
{
    public const LOGIN_SUCCEEDED = 'auth.login.succeeded';

    public const LOGIN_FAILED = 'auth.login.failed';

    public const LOGOUT = 'auth.logout';

    public const PASSWORD_CHANGED = 'auth.password.changed';

    public const PASSWORD_RESET = 'auth.password.reset';

    public const TWO_FACTOR_ENABLED = 'auth.2fa.enabled';

    public const TWO_FACTOR_DISABLED = 'auth.2fa.disabled';

    public const TWO_FACTOR_CHALLENGED = 'auth.2fa.challenged';

    public const TWO_FACTOR_CHALLENGE_FAILED = 'auth.2fa.challenge_failed';

    public const SESSION_REVOKED = 'auth.session.revoked';

    public const ACCESS_DENIED = 'auth.access.denied';

    /**
     * Enregistre un événement système, non rattaché à une ligne en base.
     */
    public function log(string $action, ?User $user = null, array $context = []): void
    {
        $this->write($action, $user, $context);
    }

    /**
     * Enregistre la création, la modification ou la suppression d'un modèle.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function logModelChange(
        string $action,
        Model $model,
        array $oldValues = [],
        array $newValues = [],
    ): void {
        $this->write($action, $this->userFor($model), [
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
        ]);
    }

    private function write(string $action, ?User $user, array $context): void
    {
        try {
            AuditLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'auditable_type' => $context['auditable_type'] ?? null,
                'auditable_id' => $context['auditable_id'] ?? null,
                'old_values' => isset($context['old_values'])
                    ? json_encode($context['old_values'], JSON_THROW_ON_ERROR)
                    : null,
                'new_values' => isset($context['new_values'])
                    ? json_encode($context['new_values'], JSON_THROW_ON_ERROR)
                    : null,
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 512),
            ]);
        } catch (Throwable $e) {
            // La journalisation ne doit jamais bloquer l'opération métier.
            // On se rabat sur le journal applicatif, qui reste consultable.
            Log::warning('Échec de l\'écriture du journal d\'audit.', [
                'action' => $action,
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function userFor(Model $model): ?User
    {
        $userId = $model->getAttribute('user_id');

        return is_string($userId) ? User::find($userId) : null;
    }

    /**
     * Retire du journal les valeurs qui n'ont pas à y figurer.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitize(array $values): array
    {
        $forbidden = ['password', 'password_confirmation', 'current_password', 'new_password', 'token'];

        return array_diff_key($values, array_flip($forbidden));
    }
}
