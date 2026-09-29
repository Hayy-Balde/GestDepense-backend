<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Account;
use App\Models\Caisse;
use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\Movement;

class ExpenseService
{
    public function __construct(
        protected CurrencyConverter $currencyConverter,
        protected MovementService $movements
    ) {}

    public function createExpense(array $data)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $expense = Expense::create($data);

            if (isset($data['account_id']) && !$expense->caisse_id) {
                $account = Account::findOrFail($data['account_id']);
                $amount = $this->convertToAccountCurrency((float) $expense->amount, $expense->currency_code, $account);
                $account->decrement('balance', $amount);

                $this->movements->record([
                    'user_id' => $expense->user_id,
                    'type' => Movement::TYPE_EXPENSE,
                    'from_type' => 'account',
                    'from_id' => $expense->account_id,
                    'amount' => $amount,
                    'currency_code' => $account->currency_code,
                    'label' => "Dépense : {$expense->title}",
                ]);
            }

            if ($expense->caisse_id) {
                $caisse = Caisse::findOrFail($expense->caisse_id);
                $this->movements->spendFromCaisse($expense->user_id, $caisse, (float) $expense->amount, "Dépense : {$expense->title}");
            }

            $this->applyToBudgetChange(
                $expense->user_id,
                $expense->category_id,
                (int) $expense->date->month,
                (int) $expense->date->year,
                (float) $expense->amount,
                +1
            );

            return $expense;
        });
    }

    public function deleteExpense(string $id)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
            $expense = Expense::findOrFail($id);

            if ($expense->account_id && !$expense->caisse_id) {
                $account = Account::findOrFail($expense->account_id);
                $amount = $this->convertToAccountCurrency((float) $expense->amount, $expense->currency_code, $account);
                $account->increment('balance', $amount);

                $this->movements->record([
                    'user_id' => $expense->user_id,
                    'type' => Movement::TYPE_EXPENSE,
                    'to_type' => 'account',
                    'to_id' => $expense->account_id,
                    'amount' => $amount,
                    'currency_code' => $account->currency_code,
                    'label' => "Suppression de dépense : {$expense->title}",
                ]);
            }

            if ($expense->caisse_id) {
                $caisse = Caisse::findOrFail($expense->caisse_id);
                $this->movements->revertExpenseFromCaisse($expense->user_id, $caisse, (float) $expense->amount, "Suppression de dépense : {$expense->title}");
            }

            $this->applyToBudgetChange(
                $expense->user_id,
                $expense->category_id,
                (int) $expense->date->month,
                (int) $expense->date->year,
                (float) $expense->amount,
                -1
            );

            return $expense->delete();
        });
    }

    public function updateExpense(string $id, array $data)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($id, $data) {
            $expense = Expense::findOrFail($id);

            $oldAccountId = $expense->account_id;
            $oldCaisseId = $expense->caisse_id;
            $oldAmount = (float) $expense->amount;
            $oldCurrency = $expense->currency_code;
            $oldUserId = $expense->user_id;
            $oldCategoryId = $expense->category_id;
            $oldMonth = (int) $expense->date->month;
            $oldYear = (int) $expense->date->year;

            $expense->update($data);

            $newAccountId = $expense->account_id;
            $newCaisseId = $expense->caisse_id;
            $newAmount = (float) $expense->amount;
            $newCurrency = $expense->currency_code;
            $newCategoryId = $expense->category_id;
            $newMonth = (int) $expense->date->month;
            $newYear = (int) $expense->date->year;

            $fundingChanged = $oldAccountId !== $newAccountId
                || $oldCaisseId !== $newCaisseId
                || $oldAmount !== $newAmount
                || $oldCurrency !== $newCurrency;

            // Restore old account balance (only if the old expense was account-funded)
            if ($oldAccountId && !$oldCaisseId) {
                $account = Account::find($oldAccountId);
                if ($account) {
                    $amount = $this->convertToAccountCurrency($oldAmount, $oldCurrency, $account);
                    $account->increment('balance', $amount);

                    if ($fundingChanged) {
                        $this->movements->record([
                            'user_id' => $expense->user_id,
                            'type' => Movement::TYPE_EXPENSE,
                            'to_type' => 'account',
                            'to_id' => $account->id,
                            'amount' => $amount,
                            'currency_code' => $account->currency_code,
                            'label' => "Correction de dépense : {$expense->title}",
                        ]);
                    }
                }
            }

            // Apply new account balance (only if the new expense is account-funded)
            if ($newAccountId && !$newCaisseId) {
                $account = Account::find($newAccountId);
                if ($account) {
                    $amount = $this->convertToAccountCurrency($newAmount, $newCurrency, $account);
                    $account->decrement('balance', $amount);

                    if ($fundingChanged) {
                        $this->movements->record([
                            'user_id' => $expense->user_id,
                            'type' => Movement::TYPE_EXPENSE,
                            'from_type' => 'account',
                            'from_id' => $account->id,
                            'amount' => $amount,
                            'currency_code' => $account->currency_code,
                            'label' => "Dépense : {$expense->title}",
                        ]);
                    }
                }
            }

            // Revert old caisse
            if ($oldCaisseId && ($oldCaisseId !== $newCaisseId || $oldAmount !== $newAmount || $oldCurrency !== $newCurrency)) {
                $oldCaisse = Caisse::find($oldCaisseId);
                if ($oldCaisse) {
                    $this->movements->revertExpenseFromCaisse($expense->user_id, $oldCaisse, $oldAmount, "Correction de dépense : {$expense->title}");
                }
            }

            // Apply new caisse
            if ($newCaisseId) {
                if (!($newCaisseId === $oldCaisseId && $oldAmount === $newAmount && $oldCurrency === $newCurrency)) {
                    $newCaisse = Caisse::findOrFail($newCaisseId);
                    $this->movements->spendFromCaisse($expense->user_id, $newCaisse, $newAmount, "Dépense : {$expense->title}");
                }
            }

            // Sync budget category: revert old, apply new
            $this->applyToBudgetChange($oldUserId, $oldCategoryId, $oldMonth, $oldYear, $oldAmount, -1);
            $this->applyToBudgetChange($expense->user_id, $newCategoryId, $newMonth, $newYear, $newAmount, +1);

            return $expense;
        });
    }

    protected function convertToAccountCurrency(float $amount, string $fromCurrency, Account $account): float
    {
        return $this->currencyConverter->convert($amount, $fromCurrency, $account->currency_code);
    }

    protected function applyToBudgetChange(string $userId, string $categoryId, int $month, int $year, float $amount, int $sign): void
    {
        if ($amount <= 0) return;

        $budget = Budget::where('user_id', $userId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if (!$budget) return;

        $budgetCategory = BudgetCategory::where('budget_id', $budget->id)
            ->where('category_id', $categoryId)
            ->first();

        if (!$budgetCategory) {
            if ($sign < 0) return;
            $budgetCategory = BudgetCategory::create([
                'budget_id' => $budget->id,
                'category_id' => $categoryId,
                'allocated_amount' => 0,
                'spent_amount' => 0,
            ]);
        }

        $delta = round($amount * $sign, 2);
        $newSpent = max(0, (float) $budgetCategory->spent_amount + $delta);
        $budgetCategory->update(['spent_amount' => $newSpent]);
    }
}
