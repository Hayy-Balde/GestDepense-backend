<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InvoicePayment;
use App\Services\Wallet\ResolvesTargets;
use Carbon\Carbon;

/**
 * Cash-flow produced by invoices.
 *
 * `issued`   → we invoice a client, the client pays us → money COMES IN  (inflow)
 * `received` → a supplier invoices us, we pay them    → money LEAVES    (outflow)
 *
 * Only payments move the treasury, and they are dated by the payment date the
 * user chose.
 *
 * Amounts are converted into the currency of the target that was debited or
 * credited, because that is the currency WalletService moved the money in.
 * Summing the raw `invoice_payments.amount` instead would make this service
 * disagree with the account or caisse balance as soon as an exchange rate exists.
 */
class InvoiceFlowService
{
    use ResolvesTargets;

    /**
     * @return array{in: float, out: float, net: float}
     */
    public function forPeriod(string $userId, Carbon $start, Carbon $end): array
    {
        $issued = 0.0;
        $received = 0.0;

        foreach ($this->payments($userId, $start, $end) as $payment) {
            if ($payment->invoice?->direction === 'issued') {
                $issued += $this->effectiveAmount($payment);
            } else {
                $received += $this->effectiveAmount($payment);
            }
        }

        return [
            'in' => round($issued, 2),
            'out' => round($received, 2),
            'net' => round($issued - $received, 2),
        ];
    }

    public function sumPayments(string $userId, string $direction, Carbon $start, Carbon $end): float
    {
        $total = 0.0;

        foreach ($this->payments($userId, $start, $end) as $payment) {
            if (($payment->invoice?->direction ?? 'received') === $direction) {
                $total += $this->effectiveAmount($payment);
            }
        }

        return round($total, 2);
    }

    /**
     * Period breakdown keyed by a date format ('Y-m' for months, 'Y-m-d' for days).
     *
     * @return array<string, array{in: float, out: float, net: float}>
     */
    public function buckets(string $userId, Carbon $start, Carbon $end, string $format): array
    {
        $buckets = [];

        foreach ($this->payments($userId, $start, $end) as $payment) {
            $key = $payment->date?->format($format);
            if (! $key) {
                continue;
            }

            $buckets[$key] ??= ['in' => 0.0, 'out' => 0.0, 'net' => 0.0];
            $amount = $this->effectiveAmount($payment);

            if ($payment->invoice?->direction === 'issued') {
                $buckets[$key]['in'] += $amount;
            } else {
                $buckets[$key]['out'] += $amount;
            }

            $buckets[$key]['net'] = round($buckets[$key]['in'] - $buckets[$key]['out'], 2);
        }

        return $buckets;
    }

    /**
     * Detail rows for the period, for the report transaction table.
     */
    public function events(string $userId, Carbon $start, Carbon $end): array
    {
        $events = [];

        foreach ($this->payments($userId, $start, $end) as $payment) {
            $invoice = $payment->invoice;
            $inflow = $invoice?->direction === 'issued';
            $ref = $invoice?->number ?: ($invoice?->counterparty ?? '');
            $party = $invoice?->counterparty ?? '';

            $events[] = [
                'date' => $payment->date?->format('Y-m-d'),
                'title' => $inflow
                    ? "Encaissement de la facture « {$ref} » (client : {$party})"
                    : "Paiement de la facture « {$ref} » (fournisseur : {$party})",
                'amount' => $this->effectiveAmount($payment),
                'currency_code' => $this->targetCurrency($payment->account, $payment->caisse, $invoice?->currency_code),
                'account' => $this->targetName($payment->account, $payment->caisse),
                'category' => $inflow ? 'Facture encaissée' : 'Facture payée',
                'source' => $this->targetName($payment->account, $payment->caisse),
                'status' => $inflow ? 'entrée' : 'sortie',
                'kind' => $inflow ? 'revenu' : 'depense',
            ];
        }

        return $events;
    }

    /**
     * Payments of the period, ordered by date.
     *
     * @return \Illuminate\Support\Collection<int, InvoicePayment>
     */
    private function payments(string $userId, Carbon $start, Carbon $end)
    {
        return InvoicePayment::with(
            'invoice:id,direction,number,counterparty,currency_code',
            'account:id,name,currency_code',
            'caisse:id,name,currency_code',
        )
            ->whereHas('invoice', fn ($q) => $q->where('user_id', $userId))
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get();
    }

    /**
     * Amount expressed in the target currency, i.e. the figure that actually
     * moved the account or caisse.
     */
    private function effectiveAmount(InvoicePayment $payment): float
    {
        return $this->amountInTargetCurrency(
            (float) $payment->amount,
            $payment->invoice?->currency_code,
            $payment->account,
            $payment->caisse,
        );
    }
}
