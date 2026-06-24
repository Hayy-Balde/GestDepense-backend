<?php
namespace App\Listeners;
use App\Events\ExpenseCreated;
use App\Models\Account;

class UpdateAccountBalance {
    public function handle(ExpenseCreated $event) {
        if ($event->expense->account_id) {
            $account = Account::find($event->expense->account_id);
            if ($account) {
                $account->balance -= $event->expense->amount;
                $account->save();
            }
        }
    }
}