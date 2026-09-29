<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Account;
use App\Models\Caisse;
use App\Models\Saving;
use App\Services\CashFlowService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __construct(private readonly CashFlowService $cashFlow) {}

    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $month = (int) $request->query('month', Carbon::now()->month);
        $year = (int) $request->query('year', Carbon::now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Debt movements are real cash flows, so they belong in every total here.
        $flow = $this->cashFlow->forPeriod($userId, $start, $end);

        $totalExpenses = (float) Expense::where('user_id', $userId)
            ->whereBetween('date', [$start, $end])
            ->sum('amount') + $flow['out'];

        $totalIncomes = (float) Income::where('user_id', $userId)
            ->whereBetween('date', [$start, $end])
            ->sum('amount') + $flow['in'];

        $availableBalance = (float) Account::where('user_id', $userId)->sum('balance');

        // L'argent immobilisé dans les caisses est mobilisable, il ne doit donc
        // pas rester invisible : on l'expose à part plutôt que de le fusionner
        // dans available_balance, dont la définition est « somme des comptes ».
        $caissesAvailable = (float) Caisse::where('user_id', $userId)
            ->where('status', 'active')
            ->get()
            ->sum(fn (Caisse $c) => (float) $c->budget_amount - (float) $c->spent_amount);
        $totalSavings = (float) Saving::where('user_id', $userId)->sum('current_amount');

        // Tendances vs mois précédent
        $prevStart = $start->copy()->subMonth();
        $prevEnd = $prevStart->copy()->endOfMonth();
        $prevFlow = $this->cashFlow->forPeriod($userId, $prevStart, $prevEnd);

        $prevExpenses = (float) Expense::where('user_id', $userId)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->sum('amount') + $prevFlow['out'];
        $prevIncomes = (float) Income::where('user_id', $userId)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->sum('amount') + $prevFlow['in'];

        $expenseTrend = $prevExpenses > 0
            ? round((($totalExpenses - $prevExpenses) / $prevExpenses) * 100, 1)
            : ($totalExpenses > 0 ? 100 : 0);

        $incomeTrend = $prevIncomes > 0
            ? round((($totalIncomes - $prevIncomes) / $prevIncomes) * 100, 1)
            : ($totalIncomes > 0 ? 100 : 0);

        $savingsRate = $totalIncomes > 0
            ? round((($totalIncomes - $totalExpenses) / $totalIncomes) * 100, 1)
            : 0;

        // Jour et semaine (mois en cours)
        $now = Carbon::now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();

        $todayFlow = $this->cashFlow->forPeriod($userId, $todayStart, $todayEnd);
        $weekFlow = $this->cashFlow->forPeriod($userId, $weekStart, $weekEnd);

        $todayExpenses = (float) Expense::where('user_id', $userId)
            ->whereBetween('date', [$todayStart, $todayEnd])
            ->sum('amount') + $todayFlow['out'];
        $todayIncomes = (float) Income::where('user_id', $userId)
            ->whereBetween('date', [$todayStart, $todayEnd])
            ->sum('amount') + $todayFlow['in'];
        $weekExpenses = (float) Expense::where('user_id', $userId)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->sum('amount') + $weekFlow['out'];
        $weekIncomes = (float) Income::where('user_id', $userId)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->sum('amount') + $weekFlow['in'];

        return response()->json([
            'total_expenses' => round($totalExpenses, 2),
            'total_incomes' => round($totalIncomes, 2),
            'available_balance' => $availableBalance,
            'total_savings' => $totalSavings,
            'expense_trend' => $expenseTrend,
            'income_trend' => $incomeTrend,
            'savings_rate' => $savingsRate,
            'burn_rate' => $totalExpenses > 0 ? round($totalExpenses / Carbon::now()->day, 2) : 0,
            'today_expenses' => round($todayExpenses, 2),
            'today_incomes' => round($todayIncomes, 2),
            'week_expenses' => round($weekExpenses, 2),
            'week_incomes' => round($weekIncomes, 2),
            'debt_in' => $flow['debts']['in'],
            'debt_out' => $flow['debts']['out'],
            'invoice_in' => $flow['invoices']['in'],
            'invoice_out' => $flow['invoices']['out'],
            'subscription_out' => $flow['subscriptions']['out'],
            'caisses_available' => round($caissesAvailable, 2),
        ]);
    }

    public function monthlySummary(Request $request)
    {
        // Mock data for trends
        return response()->json([
            ['month' => 'Jan', 'expense' => 1200, 'income' => 2000],
            ['month' => 'Feb', 'expense' => 1100, 'income' => 2000],
            ['month' => 'Mar', 'expense' => 1500, 'income' => 2100],
        ]);
    }

    public function trends(Request $request)
    {
        return response()->json(['trend' => 'stable']);
    }

    public function categoryBreakdown(Request $request)
    {
        $userId = $request->user()->id;
        // $currentMonth = Carbon::now()->startOfMonth();
        $currentMonth = Carbon::parse("$request->year-$request->month-01")->startOfMonth();
        
        $breakdown = Expense::where('user_id', $userId)
            ->where('date', '>=', $currentMonth)
            ->selectRaw('category_id, sum(amount) as total')
            ->groupBy('category_id')
            ->get();

        return response()->json($breakdown);
    }
}
