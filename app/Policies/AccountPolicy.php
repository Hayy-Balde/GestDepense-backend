<?php
namespace App\Policies;
use App\Models\User;
use App\Models\Account;

class AccountPolicy {
    public function view(User $user, Account $account) {
        return $user->id === $account->user_id;
    }
}