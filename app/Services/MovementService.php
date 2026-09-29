<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\Caisse;
use App\Models\Movement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MovementService
{
    public function __construct(private readonly CurrencyConverter $currencyConverter) {}

    /**
     * Atomically move money between an account and/or a caisse and journal it.
     * Every public operation runs inside its own DB transaction.
     */
    public function createAndFundCaisse(int|string $userId, array $data, Account $source): Caisse
    {
        return DB::transaction(function () use ($userId, $data, $source) {
            $caisseCurrency = $data['currency_code'] ?? $source->currency_code;

            // `budget_amount` est saisi dans la devise de la caisse (c'est
            // l'enveloppe qu'on crée). C'est donc le montant prélevé sur le
            // compte source qui doit être converti, sinon le solde du compte ne
            // correspondrait pas à l'argent réellement sorti.
            $caisseAmount = (float) $data['budget_amount'];
            $sourceAmount = $this->currencyConverter->convert(
                $caisseAmount,
                $caisseCurrency,
                $source->currency_code,
            );

            $this->assertEnoughBalance($source, $sourceAmount);

            $caisse = Caisse::create([
                'user_id' => $userId,
                'name' => $data['name'],
                'budget_amount' => $caisseAmount,
                'spent_amount' => 0,
                'currency_code' => $caisseCurrency,
                'source_account_id' => $source->id,
                'icon' => $data['icon'] ?? null,
                'color' => $data['color'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'active',
            ]);

            $source->decrement('balance', $sourceAmount);

            $this->record([
                'user_id' => $userId,
                'type' => Movement::TYPE_CAISSE_FUND,
                'from_type' => 'account',
                'from_id' => $source->id,
                'to_type' => 'caisse',
                'to_id' => $caisse->id,
                'amount' => $caisseAmount,
                'currency_code' => $caisse->currency_code,
                'label' => "Financement de la caisse « {$caisse->name} » depuis « {$source->name} »",
            ]);

            return $caisse;
        });
    }

    public function fundCaisse(int|string $userId, Caisse $caisse, Account $source, float $amount, ?string $label = null): Caisse
    {
        return DB::transaction(function () use ($userId, $caisse, $source, $amount, $label) {
            if ($caisse->status !== 'active') {
                throw new BusinessException('Cette caisse est clôturée, vous ne pouvez plus l’alimenter.');
            }

            // `$amount` est un apport à la caisse, donc exprimé dans sa devise.
            $caisseCurrency = $caisse->currency_code ?? $source->currency_code;
            $sourceAmount = $this->currencyConverter->convert(
                $amount,
                $caisseCurrency,
                $source->currency_code,
            );

            $this->assertEnoughBalance($source, $sourceAmount);

            $caisse->increment('budget_amount', $amount);
            $source->decrement('balance', $sourceAmount);

            $this->record([
                'user_id' => $userId,
                'type' => Movement::TYPE_CAISSE_FUND,
                'from_type' => 'account',
                'from_id' => $source->id,
                'to_type' => 'caisse',
                'to_id' => $caisse->id,
                'amount' => $amount,
                'currency_code' => $caisseCurrency,
                'label' => $label ?? "Apport de {$amount} à la caisse « {$caisse->name} »",
            ]);

            return $caisse->fresh();
        });
    }

    public function apportToAccount(int|string $userId, Account $account, float $amount, ?string $label = null): Account
    {
        return DB::transaction(function () use ($userId, $account, $amount, $label) {
            $account->increment('balance', $amount);

            $this->record([
                'user_id' => $userId,
                'type' => Movement::TYPE_APPORT,
                'from_type' => 'external',
                'to_type' => 'account',
                'to_id' => $account->id,
                'amount' => $amount,
                'currency_code' => $account->currency_code,
                'label' => $label ?? "Apport de {$amount} sur le compte « {$account->name} »",
            ]);

            return $account->fresh();
        });
    }

    public function regulate(int|string $userId, Model $entity, float $correctAmount, ?string $label = null): Model
    {
        return DB::transaction(function () use ($userId, $entity, $correctAmount, $label) {
            $entityType = $this->entityType($entity);
            $current = $this->currentAmount($entity);

            $delta = round($correctAmount - $current, 2);

            if ($delta > 0) {
                $this->credit($entity, $delta);
            } elseif ($delta < 0) {
                $this->debit($entity, abs($delta));
            }

            $this->record([
                'user_id' => $userId,
                'type' => Movement::TYPE_REGULATION,
                'from_type' => $delta < 0 ? $entityType : 'external',
                'from_id' => $delta < 0 ? $entity->id : null,
                'to_type' => $delta > 0 ? $entityType : 'external',
                'to_id' => $delta > 0 ? $entity->id : null,
                'amount' => abs($delta),
                'currency_code' => $this->currencyOf($entity),
                'label' => $label ?? "Régulation : « {$entity->name} » passe de {$current} à {$correctAmount}",
            ]);

            return $entity->fresh();
        });
    }

    public function transfer(int|string $userId, Model $from, Model $to, float $amount, ?string $label = null): array
    {
        return DB::transaction(function () use ($userId, $from, $to, $amount, $label) {
            $this->assertEnoughBalance($from, $amount);

            $fromCurrency = $this->currencyOf($from);
            $toCurrency = $this->currencyOf($to);

            // Un transfert entre une caisse et un compte peut franchir une
            // frontière de devise : le montant débité est libellé dans la
            // devise source, le montant crédité dans celle de la destination.
            $toAmount = $this->currencyConverter->convert($amount, $fromCurrency, $toCurrency);

            $this->debit($from, $amount);
            $this->credit($to, $toAmount);

            $this->record([
                'user_id' => $userId,
                'type' => Movement::TYPE_TRANSFER,
                'from_type' => $this->entityType($from),
                'from_id' => $from->id,
                'to_type' => $this->entityType($to),
                'to_id' => $to->id,
                'amount' => $amount,
                'currency_code' => $fromCurrency,
                'label' => $label ?? "Transfert de {$amount} de « {$from->name} » vers « {$to->name} »",
            ]);

            return [$from->fresh(), $to->fresh()];
        });
    }

    public function closeCaisse(int|string $userId, Caisse $caisse, ?Account $destAccount = null): Caisse
    {
        return DB::transaction(function () use ($userId, $caisse, $destAccount) {
            if ($caisse->status !== 'active') {
                throw new BusinessException('Cette caisse est déjà clôturée.');
            }

            $remaining = (float) $caisse->budget_amount - (float) $caisse->spent_amount;

            if ($remaining > 0) {
                if (!$destAccount) {
                    throw new BusinessException('La caisse contient encore ' . $remaining . ' : choisissez un compte de destination.');
                }

                // Le reste est libellé dans la devise de la caisse : le compte
                // de destination doit recevoir l'équivalent dans la sienne.
                $caisseCurrency = $this->currencyOf($caisse);
                $destAmount = $this->currencyConverter->convert(
                    $remaining,
                    $caisseCurrency,
                    $destAccount->currency_code,
                );

                $destAccount->increment('balance', $destAmount);

                $this->record([
                    'user_id' => $userId,
                    'type' => Movement::TYPE_CAISSE_REFUND,
                    'from_type' => 'caisse',
                    'from_id' => $caisse->id,
                    'to_type' => 'account',
                    'to_id' => $destAccount->id,
                    'amount' => $destAmount,
                    'currency_code' => $destAccount->currency_code,
                    'label' => "Restitution de la caisse « {$caisse->name} » vers « {$destAccount->name} »",
                ]);
            } else {
                $this->record([
                    'user_id' => $userId,
                    'type' => Movement::TYPE_CAISSE_CLOSE,
                    'from_type' => 'caisse',
                    'from_id' => $caisse->id,
                    'amount' => 0,
                    'currency_code' => $this->currencyOf($caisse),
                    'label' => "Clôture de la caisse « {$caisse->name} » (solde restant : {$remaining})",
                ]);
            }

            $caisse->update(['status' => 'closed']);
            return $caisse->fresh();
        });
    }

    /**
     * Resolve money when deleting an account or caisse.
     * mode = 'lost' (money disappears) | 'redirect' (money goes to a destination account).
     */
    public function deleteResolve(int|string $userId, Model $entity, string $mode, ?Account $destAccount = null): void
    {
        DB::transaction(function () use ($userId, $entity, $mode, $destAccount) {
            $entityType = $this->entityType($entity);

            // Pour une caisse, seul le reste à dépenser est encore mobilisable ;
            // ce qui a déjà été dépensé n'a plus à être restitué. Pour un
            // compte, on prend le solde.
            $remaining = $entity instanceof Caisse
                ? (float) $entity->budget_amount - (float) $entity->spent_amount
                : (float) $entity->balance;

            if ($remaining < 0) {
                $remaining = 0;
            }

            if ($remaining > 0) {
                if ($mode === 'redirect') {
                    if (!$destAccount) {
                        throw new BusinessException('Redirection demandée : choisissez un compte de destination.');
                    }

                    $entityCurrency = $this->currencyOf($entity);
                    $destAmount = $this->currencyConverter->convert(
                        $remaining,
                        $entityCurrency,
                        $destAccount->currency_code,
                    );

                    $this->credit($destAccount, $destAmount);

                    $this->record([
                        'user_id' => $userId,
                        'type' => Movement::TYPE_TRANSFER,
                        'from_type' => $entityType,
                        'from_id' => $entity->id,
                        'to_type' => 'account',
                        'to_id' => $destAccount->id,
                        'amount' => $destAmount,
                        'currency_code' => $destAccount->currency_code,
                        'label' => "Suppression : « {$entity->name} » redirigé vers « {$destAccount->name} »",
                    ]);
                } else {
                    $this->record([
                        'user_id' => $userId,
                        'type' => Movement::TYPE_OUT,
                        'from_type' => $entityType,
                        'from_id' => $entity->id,
                        'amount' => $remaining,
                        'currency_code' => $this->currencyOf($entity),
                        'label' => "Suppression : « {$entity->name} » — montant {$remaining} perdu",
                    ]);
                }
            }

            $entity->delete();
        });
    }

    public function spendFromCaisse(int|string $userId, Caisse $caisse, float $amount, ?string $label = null): void
    {
        $remaining = (float) $caisse->budget_amount - (float) $caisse->spent_amount;
        if ($remaining < $amount) {
            throw new BusinessException(
                "Quota de la caisse « {$caisse->name} » dépassé : restant {$remaining}, dépense {$amount}. Étendez la caisse ou choisissez un autre moyen."
            );
        }

        DB::transaction(function () use ($userId, $caisse, $amount, $label) {
            $caisse->increment('spent_amount', $amount);

            $this->record([
                'user_id' => $userId,
                'type' => Movement::TYPE_EXPENSE,
                'from_type' => 'caisse',
                'from_id' => $caisse->id,
                'amount' => $amount,
                'currency_code' => $this->currencyOf($caisse),
                'label' => $label ?? "Dépense de {$amount} sur la caisse « {$caisse->name} »",
            ]);
        });
    }

    public function revertExpenseFromCaisse(int|string $userId, Caisse $caisse, float $amount, ?string $label = null): void
    {
        DB::transaction(function () use ($userId, $caisse, $amount, $label) {
            // On ne rend jamais plus que ce qui a été consommé, sinon le quota
            // restant deviendrait supérieur au budget alloué.
            $caisse->decrement('spent_amount', min($amount, (float) $caisse->spent_amount));

            $this->record([
                'user_id' => $userId,
                'type' => Movement::TYPE_EXPENSE,
                'from_type' => 'external',
                'to_type' => 'caisse',
                'to_id' => $caisse->id,
                'amount' => $amount,
                'currency_code' => $this->currencyOf($caisse),
                'label' => $label ?? "Annulation de dépense de {$amount} sur la caisse « {$caisse->name} »",
            ]);
        });
    }

    public function record(array $data): Movement
    {
        // Sans date explicite, le mouvement est daté du jour où il est écrit :
        // sinon il disparaît de tout filtrage par période.
        if (empty($data['date'])) {
            $data['date'] = now()->format('Y-m-d');
        }

        return Movement::create($data);
    }

    protected function assertEnoughBalance(Model $entity, float $amount): void
    {
        if ($this->currentAmount($entity) < $amount) {
            throw new BusinessException("Solde insuffisant sur « {$entity->name} » ({$this->currentAmount($entity)}).");
        }
    }

    protected function credit(Model $entity, float $amount): void
    {
        if ($entity instanceof Account) {
            $entity->increment('balance', $amount);
        } elseif ($entity instanceof Caisse) {
            $entity->increment('budget_amount', $amount);
        }
    }

    protected function debit(Model $entity, float $amount): void
    {
        if ($entity instanceof Account) {
            $entity->decrement('balance', $amount);
        } elseif ($entity instanceof Caisse) {
            $entity->decrement('budget_amount', $amount);
        }
    }

    protected function currentAmount(Model $entity): float
    {
        return (float) ($entity instanceof Account ? $entity->balance : $entity->budget_amount);
    }

    protected function currencyOf(Model $entity): string
    {
        if ($entity instanceof Account) {
            return $entity->currency_code;
        }

        // La caisse a sa propre devise ; l'héritage du compte source ne sert
        // plus que de repli pour les caisses créées avant la colonne.
        return $entity->currency_code
            ?? $entity->sourceAccount?->currency_code
            ?? 'EUR';
    }

    protected function entityType(Model $entity): string
    {
        return $entity instanceof Account ? 'account' : 'caisse';
    }
}