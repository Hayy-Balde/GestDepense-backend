<?php
namespace App\Listeners;
use App\Events\ExpenseCreated;

class CheckBudgetLimit {
    public function handle(ExpenseCreated $event) {
        // Logic to check if budget exceeded
    }
}