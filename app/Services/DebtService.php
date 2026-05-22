<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\DebtPayment;
use Illuminate\Support\Facades\DB;

class DebtService
{
    public function createDebt(array $data): Debt
    {
        $data['remaining_amount'] = $data['amount'];
        $data['status'] = 'pending';
        return Debt::create($data);
    }

    public function recordPayment(Debt $debt, array $data): DebtPayment
    {
        return DB::transaction(function () use ($debt, $data) {
            $payment = DebtPayment::create([
                'debt_id' => $debt->id,
                'amount' => $data['amount'],
                'date' => $data['date'],
                'note' => $data['note'] ?? null,
            ]);

            $debt->remaining_amount -= $data['amount'];
            
            if ($debt->remaining_amount <= 0) {
                $debt->status = 'paid';
                $debt->remaining_amount = 0; // Ensure it doesn't go negative
            }

            $debt->save();

            return $payment;
        });
    }
}
