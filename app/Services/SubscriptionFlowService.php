<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Caisse;
use App\Models\Movement;
use Carbon\Carbon;

/**
 * Cash-flow produced by paid subscriptions.
 *
 * Un abonnement payé est une sortie d'argent. Contrairement aux dettes et aux
 * factures, il n'existe aucune table de paiements : la ligne de journal est
 * l'unique trace de l'encaissement, c'est donc elle qui est interrogée.
 */
class SubscriptionFlowService
{
    /**
     * @return array{in: float, out: float, net: float}
     */
    public function forPeriod(string $userId, Carbon $start, Carbon $end): array
    {
        $out = (float) Movement::where('user_id', $userId)
            ->where('related_type', 'subscription')
            ->whereBetween('date', [$start, $end])
            ->sum('amount');

        return ['in' => 0.0, 'out' => round($out, 2), 'net' => round(-$out, 2)];
    }

    /**
     * @return array<string, array{in: float, out: float, net: float}>
     */
    public function buckets(string $userId, Carbon $start, Carbon $end, string $format): array
    {
        $payments = Movement::where('user_id', $userId)
            ->where('related_type', 'subscription')
            ->whereBetween('date', [$start, $end])
            ->get(['date', 'amount']);

        $buckets = [];

        foreach ($payments as $payment) {
            $key = $payment->date?->format($format);
            if (! $key) {
                continue;
            }

            $buckets[$key] ??= ['in' => 0.0, 'out' => 0.0, 'net' => 0.0];
            $buckets[$key]['out'] += (float) $payment->amount;
            $buckets[$key]['net'] = round($buckets[$key]['in'] - $buckets[$key]['out'], 2);
        }

        return $buckets;
    }

    /**
     * Detail rows for the period, for the report transaction table.
     */
    public function events(string $userId, Carbon $start, Carbon $end): array
    {
        return Movement::where('user_id', $userId)
            ->where('related_type', 'subscription')
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get()
            ->map(fn (Movement $m) => [
                'date' => $m->date?->format('Y-m-d'),
                'title' => $m->label ?? 'Abonnement',
                'amount' => (float) $m->amount,
                'currency_code' => $m->currency_code,
                'account' => $this->targetNameOf($m),
                'category' => 'Abonnement',
                'source' => $this->targetNameOf($m),
                'status' => 'sortie',
                'kind' => 'depense',
            ])
            ->all();
    }

    /**
     * Nom de la cible débitée : le journal indique déjà le type et l'id.
     */
    private function targetNameOf(Movement $movement): ?string
    {
        if (! $movement->from_id) {
            return null;
        }

        if ($movement->from_type === 'caisse') {
            return Caisse::where('id', $movement->from_id)->value('name');
        }

        if ($movement->from_type === 'account') {
            return Account::where('id', $movement->from_id)->value('name');
        }

        return null;
    }
}
