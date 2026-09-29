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
    /**
     * Atomically move money between an account and/or a caisse and journal it.
     * Every public operation runs inside its own DB transaction.
     */
    public function createAndFundCaisse(int|string $userId, array $data, Account $source): Caisse
    {
        return DB::transaction(function () use ($userId, $data, $source) {
            $amount = (float) $data['budget_amount'];
            $this->assertEnoughBalance($source, $amount);

            $caisse = Caisse::create([
                'user_id' => $userId,
                'name' => $data['name'],
                'budget_amount' => $amount,
                'spent_amount' => 0,
                'source_account_id' => $source->id,
                'icon' => $data['icon'] ?? null,
                'color' => $data['color'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'active',
            ]);

            $source->decrement('balance', $amount);

            $this->record([
                'user_id' => $userId,
                'type' => Movement::TYPE_CAISSE_FUND,
                'from_type' => 'account',
                'from_id' => $source->id,
                'to_type' => 'caisse',
                'to_id' => $caisse->id,
                'amount' => $amount,
                'currency_code' => $source->currency_code,
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
            $this->assertEnoughBalance($source, $amount);

            $caisse->increment('budget_amount', $amount);
            $source->decrement('balance', $amount);

            $this->record([
                'user_id' => $userId,
                'type' => Movement::TYPE_CAISSE_FUND,
                'from_type' => 'account',
                'from_id' => $source->id,
                'to_type' => 'caisse',
                'to_id' => $caisse->id,
                'amount' => $amount,
                'currency_code' => $source->currency_code,
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

            $this->debit($from, $amount);
            $this->credit($to, $amount);

            $this->record([
                'user_id' => $userId,
                'type' => Movement::TYPE_TRANSFER,
                'from_type' => $this->entityType($from),
                'from_id' => $from->id,
                'to_type' => $this->entityType($to),
                'to_id' => $to->id,
                'amount' => $amount,
                'currency_code' => $this->currencyOf($from),
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
                $destAccount->increment('balance', $remaining);

                $this->record([
                    'user_id' => $userId,
                    'type' => Movement::TYPE_CAISSE_REFUND,
                    'from_type' => 'caisse',
                    'from_id' => $caisse->id,
                    'to_type' => 'account',
                    'to_id' => $destAccount->id,
                    'amount' => $remaining,
                    'currency_code' => $this->currencyOf($caisse),
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
            $remaining = $this->currentAmount($entity);

            if ($remaining < 0) {
                $remaining = 0;
            }

            if ($remaining > 0) {
                if ($mode === 'redirect') {
                    if (!$destAccount) {
                        throw new BusinessException('Redirection demandée : choisissez un compte de destination.');
                    }
                    $this->credit($destAccount, $remaining);

                    $this->record([
                        'user_id' => $userId,
                        'type' => Movement::TYPE_TRANSFER,
                        'from_type' => $entityType,
                        'from_id' => $entity->id,
                        'to_type' => 'account',
                        'to_id' => $destAccount->id,
                        'amount' => $remaining,
                        'currency_code' => $this->currencyOf($entity),
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
            $caisse->decrement('spent_amount', $amount);

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
        return $entity instanceof Account ? $entity->currency_code : ($entity->sourceAccount?->currency_code ?? 'EUR');
    }

    protected function entityType(Model $entity): string
    {
        return $entity instanceof Account ? 'account' : 'caisse';
    }
}