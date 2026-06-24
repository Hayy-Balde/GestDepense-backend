<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Account;

class ExpenseService
{
    public function createExpense(array $data)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $expense = Expense::create($data);

            if (isset($data['account_id'])) {
                $account = Account::findOrFail($data['account_id']);
                $account->balance -= $expense->amount;
                $account->save();
            }

            return $expense;
        });
    }

    public function deleteExpense(string $id)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
            $expense = Expense::findOrFail($id);
            
            if ($expense->account_id) {
                $account = Account::findOrFail($expense->account_id);
                $account->balance += $expense->amount;
                $account->save();
            }

            return $expense->delete();
        });
    }
}
