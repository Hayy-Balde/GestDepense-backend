<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

/**
 * Point d'entrée unique des flux de trésorerie qui ne passent pas par les tables
 * `incomes` / `expenses`.
 *
 * Les dettes, les factures et les abonnements déplacent réellement de l'argent,
 * mais ils sont enregistrés dans leurs propres tables : si les rapports ne les
 * additionnent pas explicitement, le solde net est faux. Ce service les regroupe
 * pour que ReportController, DashboardController et AnalyticsController n'aient
 * qu'une dépendance à maintenir.
 */
class CashFlowService
{
    public function __construct(
        private readonly DebtFlowService $debts,
        private readonly InvoiceFlowService $invoices,
        private readonly SubscriptionFlowService $subscriptions,
    ) {}

    /**
     * @return array{in: float, out: float, net: float, debts: array, invoices: array, subscriptions: array}
     */
    public function forPeriod(string $userId, Carbon $start, Carbon $end): array
    {
        $debts = $this->debts->forPeriod($userId, $start, $end);
        $invoices = $this->invoices->forPeriod($userId, $start, $end);
        $subscriptions = $this->subscriptions->forPeriod($userId, $start, $end);

        $in = round($debts['in'] + $invoices['in'] + $subscriptions['in'], 2);
        $out = round($debts['out'] + $invoices['out'] + $subscriptions['out'], 2);

        return [
            'in' => $in,
            'out' => $out,
            'net' => round($in - $out, 2),
            'debts' => $debts,
            'invoices' => $invoices,
            'subscriptions' => $subscriptions,
        ];
    }

    /**
     * Entrées et sorties pour une période, dettes et factures confondues.
     *
     * @return array{in: float, out: float, net: float}
     */
    public function totals(string $userId, Carbon $start, Carbon $end): array
    {
        $flow = $this->forPeriod($userId, $start, $end);

        return ['in' => $flow['in'], 'out' => $flow['out'], 'net' => $flow['net']];
    }

    /**
     * Répartition par jour ou par mois, sur la même convention de clés que les
     * graphiques existing (`Y-m-d` ou `Y-m`).
     *
     * @return array<string, array{in: float, out: float, net: float}>
     */
    public function buckets(string $userId, Carbon $start, Carbon $end, string $format): array
    {
        $buckets = [];

        $merge = function (array $source) use (&$buckets) {
            foreach ($source as $key => $flow) {
                $buckets[$key] ??= ['in' => 0.0, 'out' => 0.0, 'net' => 0.0];
                $buckets[$key]['in'] += $flow['in'];
                $buckets[$key]['out'] += $flow['out'];
            }
        };

        $merge($this->debts->buckets($userId, $start, $end, $format));
        $merge($this->invoices->buckets($userId, $start, $end, $format));
        $merge($this->subscriptions->buckets($userId, $start, $end, $format));

        foreach ($buckets as $key => $flow) {
            $buckets[$key]['net'] = round($flow['in'] - $flow['out'], 2);
        }

        return $buckets;
    }

    /**
     * Lignes de détail des opérations de dettes, factures et abonnements de la
     * période, triées par date.
     */
    public function events(string $userId, Carbon $start, Carbon $end): array
    {
        $events = array_merge(
            $this->debts->events($userId, $start, $end),
            $this->invoices->events($userId, $start, $end),
            $this->subscriptions->events($userId, $start, $end)
        );

        usort($events, fn ($a, $b) => ($a['date'] ?? '') <=> ($b['date'] ?? ''));

        return $events;
    }
}
