<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\Account;
use App\Models\Caisse;
use App\Services\CurrencyConverter;

/**
 * Traduit une ligne de règlement (dette, facture, abonnement) en « cible »
 * affichable et en montant comparable.
 *
 * Une opération peut être adossée à un compte ou à une caisse. Les deux ont
 * une devise, mais pas la même source : un compte a la sienne, une caisse
 * celle de sa colonne `currency_code` (héritée de son compte source à la
 * création). Sans cette normalisation, le rapport afficherait des montants
 * dans des devises différentes et le total ne voudrait rien dire.
 */
trait ResolvesTargets
{
    /**
     * Nom de la cible, quel que soit son type, pour l'affichage.
     */
    protected function targetName(mixed $account, mixed $caisse): ?string
    {
        if ($account instanceof Account) {
            return $account->name;
        }

        if ($caisse instanceof Caisse) {
            return $caisse->name;
        }

        return null;
    }

    /**
     * Devise de la cible, avec repli sur celle de l'opération.
     */
    protected function targetCurrency(mixed $account, mixed $caisse, ?string $fallback = null): string
    {
        if ($account instanceof Account) {
            return $account->currency_code;
        }

        if ($caisse instanceof Caisse) {
            return $caisse->currency_code
                ?? $caisse->sourceAccount?->currency_code
                ?? $fallback
                ?? 'EUR';
        }

        return $fallback ?? 'EUR';
    }

    /**
     * Montant exprimé dans la devise de la cible — c'est-à-dire la valeur qui a
     * réellement quitté ou rejoint le compte ou la caisse.
     */
    protected function amountInTargetCurrency(
        float $amount,
        ?string $from,
        mixed $account,
        mixed $caisse,
        ?string $fallback = null,
    ): float {
        $to = $this->targetCurrency($account, $caisse, $fallback);

        if (! $from || $from === $to) {
            return $amount;
        }

        return app(CurrencyConverter::class)->convert($amount, $from, $to);
    }
}
