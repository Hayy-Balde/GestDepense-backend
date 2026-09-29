<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Caisse;
use App\Models\Expense;
use App\Models\Income;
use App\Services\CashFlowService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function __construct(private readonly CashFlowService $cashFlow) {}

    /**
     * Résumé de la période (un ou plusieurs mois) : état actuel des comptes,
     * tableau des revenus, tableau des dépenses, opérations de dettes, et totaux.
     *
     * Les dettes sont de véritables flux de trésorerie : un prêt accordé est une
     * sortie, un emprunt reçu une entrée, un remboursement reçu une entrée et un
     * remboursement versé une sortie. Les omettre fausserait le solde net.
     */
    public function summary(Request $request)
    {
        $userId = $request->user()->id;

        Carbon::setLocale('fr');

        $startMonth = (int) $request->query('start_month', Carbon::now()->month);
        $startYear = (int) $request->query('start_year', Carbon::now()->year);
        $endMonth = (int) $request->query('end_month', Carbon::now()->month);
        $endYear = (int) $request->query('end_year', Carbon::now()->year);

        $start = Carbon::create($startYear, $startMonth, 1)->startOfMonth();
        $end = Carbon::create($endYear, $endMonth, 1)->endOfMonth();

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy(), $start->copy()];
            $start = $start->startOfMonth();
            $end = $end->endOfMonth();
        }

        $accounts = Account::where('user_id', $userId)->orderBy('name')->get()
            ->map(fn (Account $a) => [
                'name' => $a->name,
                'type' => $a->type?->value ?? $a->type,
                'currency_code' => $a->currency_code,
                'balance' => (float) $a->balance,
            ]);

        $caisses = Caisse::with('sourceAccount')->where('user_id', $userId)->orderBy('name')->get()
            ->map(fn (Caisse $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'status' => $c->status,
                // La caisse porte sa propre devise ; le compte source n'est plus
                // qu'un repli pour les caisses créées avant la colonne.
                'currency_code' => $c->currency_code
                    ?? $c->sourceAccount?->currency_code
                    ?? 'EUR',
                'budget_amount' => (float) $c->budget_amount,
                'spent_amount' => (float) $c->spent_amount,
                'remaining' => round((float) $c->budget_amount - (float) $c->spent_amount, 2),
            ]);

        $incomes = Income::with('account', 'category')
            ->where('user_id', $userId)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get()
            ->map(fn (Income $i) => [
                'date' => $i->date?->format('Y-m-d'),
                'title' => $i->title,
                'amount' => (float) $i->amount,
                'currency_code' => $i->currency_code,
                'account' => $i->account?->name,
                'category' => $i->category?->name,
            ]);

        $expenses = Expense::with('account', 'category', 'caisse')
            ->where('user_id', $userId)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get()
            ->map(fn (Expense $e) => [
                'date' => $e->date?->format('Y-m-d'),
                'title' => $e->title,
                'amount' => (float) $e->amount,
                'currency_code' => $e->currency_code,
                'source' => $e->caisse?->name ?? $e->account?->name,
                'category' => $e->category?->name,
                'status' => $e->status?->value ?? $e->status,
            ]);

        $cashFlow = $this->cashFlow->forPeriod($userId, $start, $end);
        $cashEvents = $this->cashFlow->events($userId, $start, $end);

        $totalIncomes = (float) $incomes->sum('amount') + $cashFlow['in'];
        $totalExpenses = (float) $expenses->sum('amount') + $cashFlow['out'];

        $totals = [
            'incomes' => round($totalIncomes, 2),
            'expenses' => round($totalExpenses, 2),
            'net' => round($totalIncomes - $totalExpenses, 2),
            'incomes_base' => (float) $incomes->sum('amount'),
            'expenses_base' => (float) $expenses->sum('amount'),
            'debt_in' => $cashFlow['debts']['in'],
            'debt_out' => $cashFlow['debts']['out'],
            'invoice_in' => $cashFlow['invoices']['in'],
            'invoice_out' => $cashFlow['invoices']['out'],
            'subscription_out' => $cashFlow['subscriptions']['out'],
        ];

        return response()->json([
            'interval' => [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'label' => $start->format('Y-m') === $end->format('Y-m')
                    ? $start->translatedFormat('F Y')
                    : $start->translatedFormat('M Y') . ' – ' . $end->translatedFormat('M Y'),
            ],
            'accounts' => $accounts->values(),
            'caisses' => $caisses->values(),
            'incomes' => $incomes->values(),
            'expenses' => $expenses->values(),
            'cash_events' => $cashEvents,
            'cash_flow' => $cashFlow,
            'totals' => $totals,
        ]);
    }
}