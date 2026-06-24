<?php

namespace App\Services;

use App\Models\Caisse;
use App\Models\Expense;

class CaisseService
{
    public function createCaisse(array $data): Caisse
    {
        return Caisse::create($data);
    }

    public function updateCaisse(Caisse $caisse, array $data): Caisse
    {
        $caisse->update($data);
        return $caisse;
    }

    public function calculateStats(string $caisseId): array
    {
        $caisse = Caisse::findOrFail($caisseId);
        $totalSpent = Expense::where('caisse_id', $caisseId)->sum('amount');

        return [
            'budget_amount' => $caisse->budget_amount,
            'total_spent' => (float) $totalSpent,
            'remaining_budget' => $caisse->budget_amount - $totalSpent,
            'percentage_used' => $caisse->budget_amount > 0 ? ($totalSpent / $caisse->budget_amount) * 100 : 0
        ];
    }
}
