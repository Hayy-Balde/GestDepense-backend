<?php
namespace App\Policies;
use App\Models\User;
use App\Models\Expense;

class ExpensePolicy {
    public function view(User $user, Expense $expense) {
        return $user->id === $expense->user_id;
    }
    public function update(User $user, Expense $expense) {
        return $user->id === $expense->user_id;
    }
    public function delete(User $user, Expense $expense) {
        return $user->id === $expense->user_id;
    }
}