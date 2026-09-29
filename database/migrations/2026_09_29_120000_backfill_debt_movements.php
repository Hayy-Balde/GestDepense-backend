<?php

use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Movement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Debt::with('account:id,currency_code')
            ->chunkById(200, function ($debts) {
                foreach ($debts as $debt) {
                    $lent = $debt->type === 'lent';
                    $currency = $debt->currency_code ?? $debt->account?->currency_code ?? 'EUR';

                    $exists = Movement::where('related_type', 'debt')
                        ->where('related_id', $debt->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    Movement::create([
                        'user_id' => $debt->user_id,
                        'type' => $lent ? Movement::TYPE_DEBT_LEND : Movement::TYPE_DEBT_BORROW,
                        'related_type' => 'debt',
                        'related_id' => $debt->id,
                        'date' => $debt->created_at?->format('Y-m-d'),
                        'from_type' => $lent ? 'account' : 'external',
                        'from_id' => $lent ? $debt->account_id : null,
                        'to_type' => $lent ? 'external' : 'account',
                        'to_id' => $lent ? null : $debt->account_id,
                        'amount' => (float) $debt->amount,
                        'currency_code' => $currency,
                        'label' => ($lent ? 'Prêt accordé : ' : 'Emprunt : ') . $debt->person_name,
                    ]);
                }
            });

        DebtPayment::with('debt:id,user_id,type,person_name,currency_code', 'account:id,currency_code')
            ->chunkById(200, function ($payments) {
                foreach ($payments as $payment) {
                    $debt = $payment->debt;
                    if (! $debt) {
                        continue;
                    }

                    $exists = Movement::where('related_type', 'debt_payment')
                        ->where('related_id', $payment->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $inflow = $debt->type === 'lent';

                    Movement::create([
                        'user_id' => $debt->user_id,
                        'type' => Movement::TYPE_DEBT_REPAYMENT,
                        'related_type' => 'debt_payment',
                        'related_id' => $payment->id,
                        'date' => $payment->date?->format('Y-m-d'),
                        'from_type' => $inflow ? 'external' : 'account',
                        'from_id' => $inflow ? null : $payment->account_id,
                        'to_type' => $inflow ? 'account' : 'external',
                        'to_id' => $inflow ? $payment->account_id : null,
                        'amount' => (float) $payment->amount,
                        'currency_code' => $debt->currency_code ?? $payment->account?->currency_code ?? 'EUR',
                        'label' => ($inflow ? 'Remboursement reçu : ' : 'Remboursement versé : ') . $debt->person_name,
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('movements')
            ->whereIn('type', [
                Movement::TYPE_DEBT_LEND,
                Movement::TYPE_DEBT_BORROW,
                Movement::TYPE_DEBT_REPAYMENT,
                Movement::TYPE_DEBT_REVERSAL,
            ])
            ->delete();
    }
};
