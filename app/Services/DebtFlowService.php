<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Debt;
use App\Models\DebtPayment;
use App\Services\Wallet\ResolvesTargets;
use Carbon\Carbon;

/**
 * Cash-flow produced by debts.
 *
 * A debt is a real treasury movement, so it must be part of every balance report:
 *
 *   lent      creation  -> money LEAVES the account or caisse   (outflow)
 *   lent      payment   -> money COMES BACK to the account      (inflow)
 *   borrowed  creation  -> money COMES IN to the account        (inflow)
 *   borrowed  payment   -> money LEAVES the account or caisse   (outflow)
 *
 * A repayment on `lent` and the creation of a `borrowed` are inflows, so they
 * require an account; a caisse is a spending envelope and cannot receive
 * money. WalletService enforces that, and these figures mirror exactly what it
 * moved, which is what makes `net` reconcile with the target balances.
 *
 * Dates: debt creation is dated by debts.created_at, repayments by debtpayments.date
 * (the business date chosen by the user).
 */
class DebtFlowService
{
    use ResolvesTargets;

    /**
     * @return array{in: float, out: float, net: float}
     */
    public function forPeriod(string $userId, Carbon $start, Carbon $end): array
    {
        $lent = 0.0;
        $borrowed = 0.0;

        foreach ($this->creations($userId, $start, $end) as $debt) {
            if ($debt->type === 'borrowed') {
                $borrowed += $this->creationAmount($debt);
            } else {
                $lent += $this->creationAmount($debt);
            }
        }

        $repaid = $this->repayments($userId, $start, $end);

        $in = $borrowed + $repaid['lent'];
        $out = $lent + $repaid['borrowed'];

        return [
            'in' => round($in, 2),
            'out' => round($out, 2),
            'net' => round($in - $out, 2),
        ];
    }

    /**
     * @return array{lent: float, borrowed: float}
     */
    public function repayments(string $userId, Carbon $start, Carbon $end): array
    {
        $totals = ['lent' => 0.0, 'borrowed' => 0.0];

        foreach ($this->payments($userId, $start, $end) as $payment) {
            $key = $payment->debt?->type === 'borrowed' ? 'borrowed' : 'lent';
            $totals[$key] += $this->paymentAmount($payment);
        }

        return [
            'lent' => round($totals['lent'], 2),
            'borrowed' => round($totals['borrowed'], 2),
        ];
    }

    /**
     * Month-by-month breakdown, used by the analytics charts.
     *
     * @return array<string, array{in: float, out: float, net: float}>
     */
    public function byMonth(string $userId, Carbon $start, Carbon $end): array
    {
        return $this->buckets($userId, $start, $end, 'Y-m');
    }

    /**
     * Period breakdown keyed by an arbitrary date format ('Y-m' for months,
     * 'Y-m-d' for days), so daily and monthly charts share the same code.
     *
     * @return array<string, array{in: float, out: float, net: float}>
     */
    public function buckets(string $userId, Carbon $start, Carbon $end, string $format): array
    {
        $buckets = [];

        $push = function (string $key, float $amount, bool $inflow) use (&$buckets) {
            $buckets[$key] ??= ['in' => 0.0, 'out' => 0.0, 'net' => 0.0];
            if ($inflow) {
                $buckets[$key]['in'] += $amount;
            } else {
                $buckets[$key]['out'] += $amount;
            }
            $buckets[$key]['net'] = round($buckets[$key]['in'] - $buckets[$key]['out'], 2);
        };

        foreach ($this->creations($userId, $start, $end) as $debt) {
            $push(
                $debt->created_at->format($format),
                $this->creationAmount($debt),
                $debt->type === 'borrowed',
            );
        }

        foreach ($this->payments($userId, $start, $end) as $payment) {
            $push(
                $payment->date->format($format),
                $this->paymentAmount($payment),
                $payment->debt?->type !== 'borrowed',
            );
        }

        return $buckets;
    }

    /**
     * Detail rows for the period, for the report transaction table.
     */
    public function events(string $userId, Carbon $start, Carbon $end): array
    {
        $events = [];

        foreach ($this->creations($userId, $start, $end) as $debt) {
            $inflow = $debt->type === 'borrowed';
            $target = $this->targetName($debt->account, $debt->caisse);

            $events[] = [
                'date' => $debt->created_at->format('Y-m-d'),
                'title' => ($inflow ? 'Emprunt : ' : 'Prêt accordé : ') . $debt->person_name,
                'amount' => $this->creationAmount($debt),
                'currency_code' => $this->targetCurrency($debt->account, $debt->caisse, $debt->currency_code),
                'account' => $target,
                'category' => $inflow ? 'Emprunt' : 'Prêt',
                'source' => $target,
                'status' => $inflow ? 'entrée' : 'sortie',
                'kind' => $inflow ? 'revenu' : 'depense',
            ];
        }

        foreach ($this->payments($userId, $start, $end) as $payment) {
            $debt = $payment->debt;
            $inflow = $debt?->type === 'lent';
            $target = $this->targetName($payment->account, $payment->caisse);

            $events[] = [
                'date' => $payment->date?->format('Y-m-d'),
                'title' => ($inflow ? 'Remboursement reçu : ' : 'Remboursement versé : ') . ($debt?->person_name ?? ''),
                'amount' => $this->paymentAmount($payment),
                'currency_code' => $this->targetCurrency($payment->account, $payment->caisse, $debt?->currency_code),
                'account' => $target,
                'category' => $inflow ? 'Remboursement reçu' : 'Remboursement versé',
                'source' => $target,
                'status' => $inflow ? 'entrée' : 'sortie',
                'kind' => $inflow ? 'revenu' : 'depense',
            ];
        }

        usort($events, fn ($a, $b) => ($a['date'] ?? '') <=> ($b['date'] ?? ''));

        return $events;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Debt>
     */
    private function creations(string $userId, Carbon $start, Carbon $end)
    {
        return Debt::with('account:id,name,currency_code', 'caisse:id,name,currency_code')
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, DebtPayment>
     */
    private function payments(string $userId, Carbon $start, Carbon $end)
    {
        return DebtPayment::with(
            'account:id,name,currency_code',
            'caisse:id,name,currency_code',
            'debt:id,type,person_name,currency_code',
        )
            ->whereHas('debt', fn ($q) => $q->where('user_id', $userId))
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get();
    }

    private function creationAmount(Debt $debt): float
    {
        return $this->amountInTargetCurrency(
            (float) $debt->amount,
            $debt->currency_code,
            $debt->account,
            $debt->caisse,
        );
    }

    private function paymentAmount(DebtPayment $payment): float
    {
        return $this->amountInTargetCurrency(
            (float) $payment->amount,
            $payment->debt?->currency_code,
            $payment->account,
            $payment->caisse,
        );
    }
}
