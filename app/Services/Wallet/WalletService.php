<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\Caisse;
use App\Models\Movement;
use App\Services\CurrencyConverter;
use App\Services\MovementService;
use Illuminate\Validation\ValidationException;

/**
 * Point d'entrée unique pour déplacer de l'argent depuis ou vers un compte ou
 * une caisse.
 *
 * Avant, chaque contrôleur (dettes, factures, abonnements) refaisait le même
 * travail à la main : résoudre un compte, vérifier le solde, convertir, journaler.
 * Trois Consequences en découlaient :
 *
 *  - une caisse n'était pas gérée du tout, faute de solde à débiter ;
 *  - les dettes appliquaient un montant brut alors que les factures convertis-
 *    saient en devise de compte, si bien que rapport et solde divergeaient ;
 *  - les règles de quota de caisse et de solde insuffisant vivaient dans
 *    MovementService, donc n'étaient pas appliquées uniformément.
 *
 * Ce service applique une seule sémantique à tous les flux.
 */
class WalletService
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
        private readonly MovementService $movements,
    ) {}

    /**
     * Résout la destination d'un mouvement. Exactement un des deux identifiants
     * est attendu.
     *
     * Une caisse ne peut que sortir de l'argent : c'est une enveloppe de
     * dépenses alimentée par un compte. Les entrées (encaissement, emprunt reçu)
     * doivent donc cibler un compte, sinon on créerait du quota de caisse à
     * partir de rien.
     */
    public function resolve(string $userId, ?string $accountId, ?string $caisseId, bool $inbound = false): ?MoneyTarget
    {
        if ($accountId && $caisseId) {
            throw ValidationException::withMessages([
                'account_id' => ["Choisissez soit un compte, soit une caisse, pas les deux."],
            ]);
        }

        if ($caisseId) {
            if ($inbound) {
                throw ValidationException::withMessages([
                    'caisse_id' => ["Une caisse ne peut pas encaisser d'argent : recevez sur un compte."],
                ]);
            }

            $caisse = Caisse::where('user_id', $userId)->find($caisseId);

            if (! $caisse) {
                throw ValidationException::withMessages([
                    'caisse_id' => ['Caisse introuvable.'],
                ]);
            }

            if ($caisse->status === 'closed') {
                throw new BusinessException("La caisse « {$caisse->name} » est clôturée.");
            }

            return MoneyTarget::forCaisse($caisse);
        }

        if (! $accountId) {
            return null;
        }

        $account = Account::where('user_id', $userId)->find($accountId);

        if (! $account) {
            throw ValidationException::withMessages([
                'account_id' => ['Compte introuvable.'],
            ]);
        }

        return MoneyTarget::forAccount($account);
    }

    /**
     * Débite la cible : solde du compte, ou quota consommé de la caisse.
     */
    public function debit(MoneyTarget $target, float $amount): void
    {
        $available = $target->available();

        if ($available < $amount) {
            if ($target->isCaisse()) {
                throw new BusinessException(
                    "Quota de la caisse « {$target->name} » dépassé : restant {$available}, dépense {$amount}."
                );
            }

            throw new BusinessException("Solde insuffisant sur « {$target->name} » ({$available}).");
        }

        if ($target->isCaisse()) {
            $this->caisse($target)->increment('spent_amount', $amount);

            return;
        }

        $this->account($target)->decrement('balance', $amount);
    }

    /**
     * Débite la cible sans vérifier le disponible.
     *
     * Réservé aux annulations (suppression d'une dette, d'une facture, d'un
     * abonnement). Lorsqu'on annule un flux passé, l'argent a déjà bougé : le
     * solde actuel peut être inférieur au montant à reprendre parce qu'il a été
     * dépensé depuis. Refuser l'annulation laisserait une donnée fantôme dans
     * les rapports, ce qui est pire qu'un solde momentanément négatif.
     */
    public function forceDebit(MoneyTarget $target, float $amount): void
    {
        if ($target->isCaisse()) {
            $this->caisse($target)->increment('spent_amount', $amount);

            return;
        }

        $this->account($target)->decrement('balance', $amount);
    }

    /**
     * Crédite la cible. Réservé aux comptes : une caisse ne se crédite que par
     * un apport explicite (`MovementService::fundCaisse`), pas par un flux
     * entrant, sinon son budget gonflerait sans contrepartie.
     */
    public function credit(MoneyTarget $target, float $amount): void
    {
        if ($target->isCaisse()) {
            throw new BusinessException(
                "Impossible de créditer la caisse « {$target->name} » : utilisez « Étendre » pour l'alimenter."
            );
        }

        $this->account($target)->increment('balance', $amount);
    }

    /**
     * Annule un débit : l'argent revient dans la cible. Sur une caisse, on
     * rend le quota consommé (`spent_amount`), pas le budget, sinon la
     * restitution à la clôture(Create) renverrait au compte un montant que
     * personne n'y a jamais versé.
     */
    public function refund(MoneyTarget $target, float $amount): void
    {
        if ($target->isCaisse()) {
            // On ne rend jamais plus que ce qui a été consommé, sinon
            // spent_amount passerait négatif et le quotaAvailable serait faux.
            $caisse = $this->caisse($target);
            $caisse->decrement('spent_amount', min($amount, (float) $caisse->spent_amount));

            return;
        }

        $this->account($target)->increment('balance', $amount);
    }

    /**
     * Écrit la ligne de journal correspondant au mouvement, dans la devise de
     * la cible (c'est celle qui a réellement bougé).
     *
     * `$movementType` permet à l'appelant de conserver une sémantique métier
     * (prêt accordé, encaissement de facture...) au lieu du simple
     * entrée/sortie.
     */
    public function journal(
        MoneyTarget $target,
        string $direction,
        float $amount,
        string $label,
        ?string $date = null,
        ?string $relatedType = null,
        ?string $relatedId = null,
        ?string $movementType = null,
    ): Movement {
        $outbound = $direction === 'out';

        return $this->movements->record([
            'user_id' => $target->entity->user_id,
            'type' => $movementType ?? ($outbound ? Movement::TYPE_EXPENSE : Movement::TYPE_INCOME),
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'date' => $date,
            'from_type' => $outbound ? $target->type : 'external',
            'from_id' => $outbound ? $target->id : null,
            'to_type' => $outbound ? 'external' : $target->type,
            'to_id' => $outbound ? null : $target->id,
            'amount' => $amount,
            'currency_code' => $target->currencyCode,
            'label' => $label,
        ]);
    }

    /**
     * Convertit un montant vers la devise de la cible. Sans effet si les devises
     * sont identiques, ce qui est le cas courant.
     */
    public function toTargetCurrency(float $amount, string $from, MoneyTarget $target): float
    {
        return $this->currencyConverter->convert($amount, $from, $target->currencyCode);
    }

    /**
     * Devise effective d'une cible, telle qu'elle doit être stockée sur le
     * mouvement et l'agrégat de rapport.
     */
    public function currencyOf(MoneyTarget $target): string
    {
        return $target->currencyCode;
    }

    private function account(MoneyTarget $target): Account
    {
        /** @var Account $account */
        $account = $target->entity;

        return $account;
    }

    private function caisse(MoneyTarget $target): Caisse
    {
        /** @var Caisse $caisse */
        $caisse = $target->entity;

        return $caisse;
    }
}
