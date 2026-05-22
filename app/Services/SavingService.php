<?php

namespace App\Services;

use App\Models\Saving;
use App\Models\SavingTransaction;
use Illuminate\Support\Facades\DB;

class SavingService
{
    public function createSaving(array $data): Saving
    {
        $data['current_amount'] = 0;
        return Saving::create($data);
    }

    public function processTransaction(Saving $saving, array $data, string $type): SavingTransaction
    {
        return DB::transaction(function () use ($saving, $data, $type) {
            $transaction = SavingTransaction::create([
                'saving_id' => $saving->id,
                'type' => $type,
                'amount' => $data['amount'],
                'date' => $data['date'] ?? now(),
                'note' => $data['note'] ?? null,
            ]);

            if ($type === 'deposit') {
                $saving->current_amount += $data['amount'];
            } elseif ($type === 'withdrawal') {
                $saving->current_amount -= $data['amount'];
            }

            $saving->save();

            return $transaction;
        });
    }
}
