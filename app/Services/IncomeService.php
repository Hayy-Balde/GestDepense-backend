<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Income;
use App\Models\Movement;
use Illuminate\Support\Facades\DB;

class IncomeService
{
    public function __construct(
        protected CurrencyConverter $currencyConverter,
        protected MovementService $movements
    ) {}

    public function createIncome(array $data)
    {
        return DB::transaction(function () use ($data) {
            $income = Income::create($data);

            if (isset($data['account_id'])) {
                $account = Account::findOrFail($data['account_id']);
                $amount = $this->convertToAccountCurrency((float) $income->amount, $income->currency_code, $account);
                $account->increment('balance', $amount);

                $this->movements->record([
                    'user_id' => $income->user_id,
                    'type' => Movement::TYPE_INCOME,
                    'to_type' => 'account',
                    'to_id' => $account->id,
                    'amount' => $amount,
                    'currency_code' => $account->currency_code,
                    'label' => "Revenu : {$income->title}",
                ]);
            }

            return $income;
        });
    }

    public function updateIncome(Income $income, array $data)
    {
        return DB::transaction(function () use ($income, $data) {
            unset($data['source_type'], $data['user_id']);

            $oldAccountId = $income->account_id;
            $oldAmount = (float) $income->amount;
            $oldCurrency = $income->currency_code;

            $income->update($data);

            $newAccountId = $income->account_id;
            $newAmount = (float) $income->amount;
            $newCurrency = $income->currency_code;

            $fundingChanged = $oldAccountId !== $newAccountId
                || $oldAmount !== $newAmount
                || $oldCurrency !== $newCurrency;

            if ($oldAccountId) {
                $account = Account::find($oldAccountId);
                if ($account) {
                    $amount = $this->convertToAccountCurrency($oldAmount, $oldCurrency, $account);
                    $account->decrement('balance', $amount);

                    if ($fundingChanged) {
                        $this->movements->record([
                            'user_id' => $income->user_id,
                            'type' => Movement::TYPE_INCOME,
                            'from_type' => 'account',
                            'from_id' => $account->id,
                            'amount' => $amount,
                            'currency_code' => $account->currency_code,
                            'label' => "Correction de revenu : {$income->title}",
                        ]);
                    }
                }
            }

            if ($newAccountId) {
                $account = Account::find($newAccountId);
                if ($account) {
                    $amount = $this->convertToAccountCurrency($newAmount, $newCurrency, $account);
                    $account->increment('balance', $amount);

                    if ($fundingChanged) {
                        $this->movements->record([
                            'user_id' => $income->user_id,
                            'type' => Movement::TYPE_INCOME,
                            'to_type' => 'account',
                            'to_id' => $account->id,
                            'amount' => $amount,
                            'currency_code' => $account->currency_code,
                            'label' => "Revenu : {$income->title}",
                        ]);
                    }
                }
            }

            return $income;
        });
    }

    public function deleteIncome(Income $income)
    {
        return DB::transaction(function () use ($income) {
            if ($income->account_id) {
                $account = Account::find($income->account_id);
                if ($account) {
                    $amount = $this->convertToAccountCurrency((float) $income->amount, $income->currency_code, $account);
                    $account->decrement('balance', $amount);

                    $this->movements->record([
                        'user_id' => $income->user_id,
                        'type' => Movement::TYPE_INCOME,
                        'from_type' => 'account',
                        'from_id' => $account->id,
                        'amount' => $amount,
                        'currency_code' => $account->currency_code,
                        'label' => "Suppression de revenu : {$income->title}",
                    ]);
                }
            }

            return $income->delete();
        });
    }

    protected function convertToAccountCurrency(float $amount, string $fromCurrency, Account $account): float
    {
        return $this->currencyConverter->convert($amount, $fromCurrency, $account->currency_code);
    }
}