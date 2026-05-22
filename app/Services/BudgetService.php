<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetCategory;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    public function createOrUpdateBudget(array $data): Budget
    {
        return DB::transaction(function () use ($data) {
            $budget = Budget::updateOrCreate(
                [
                    'user_id' => $data['user_id'],
                    'month' => $data['month'],
                    'year' => $data['year']
                ],
                [
                    'total_budget' => $data['total_budget'],
                    'notes' => $data['notes'] ?? null
                ]
            );

            if (isset($data['categories']) && is_array($data['categories'])) {
                // Sync categories
                BudgetCategory::where('budget_id', $budget->id)->delete();
                foreach ($data['categories'] as $cat) {
                    BudgetCategory::create([
                        'budget_id' => $budget->id,
                        'category_id' => $cat['category_id'],
                        'allocated_amount' => $cat['allocated_amount']
                    ]);
                }
            }

            return $budget->load('budgetCategories');
        });
    }

    public function checkBudgetLimit(string $userId, string $categoryId, float $expenseAmount, int $month, int $year): bool
    {
        $budget = Budget::where('user_id', $userId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if (!$budget) return false;

        $budgetCategory = BudgetCategory::where('budget_id', $budget->id)
            ->where('category_id', $categoryId)
            ->first();

        if (!$budgetCategory) return false;

        $currentSpent = \App\Models\Expense::where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->sum('amount');

        return ($currentSpent + $expenseAmount) > $budgetCategory->allocated_amount;
    }
}
