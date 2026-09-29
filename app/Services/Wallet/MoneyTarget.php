<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\Account;
use App\Models\Caisse;
use Illuminate\Database\Eloquent\Model;

/**
 * Destination d'un mouvement d'argent : soit un compte, soit une caisse.
 *
 * Les deux ne se pilotent pas de la même façon. Un compte porte un solde
 * absolu ; une caisse est une enveloppe, alimentée par un compte source et
 * consommée via `spent_amount`. Cette classe masque la différence pour que les
 * appelants n'aient à connaître que trois notions : le disponible, la devise et
 * le sens du mouvement.
 */
final class MoneyTarget
{
    public const ACCOUNT = 'account';
    public const CAISSE = 'caisse';

    private function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly string $name,
        public readonly string $currencyCode,
        public readonly Model $entity,
    ) {}

    public static function forAccount(Account $account): self
    {
        return new self(self::ACCOUNT, $account->id, $account->name, $account->currency_code, $account);
    }

    public static function forCaisse(Caisse $caisse): self
    {
        return new self(
            self::CAISSE,
            $caisse->id,
            $caisse->name,
            $caisse->currency_code
                ?? $caisse->sourceAccount?->currency_code
                ?? 'EUR',
            $caisse,
        );
    }

    public function isCaisse(): bool
    {
        return $this->type === self::CAISSE;
    }

    /**
     * Argent réellement mobilisable : solde du compte, ou quota restant de la
     * caisse (ce qu'on y a mis moins ce qu'on y a déjà dépensé).
     */
    public function available(): float
    {
        if ($this->entity instanceof Account) {
            return (float) $this->entity->balance;
        }

        /** @var Caisse $caisse */
        $caisse = $this->entity;

        return (float) $caisse->budget_amount - (float) $caisse->spent_amount;
    }

    public function isClosed(): bool
    {
        return $this->entity instanceof Caisse && $this->entity->status === 'closed';
    }
}
