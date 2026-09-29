<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Account;
use App\Models\Saving;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $month = (int) $request->query('month', Carbon::now()->month);
        $year = (int) $request->query('year', Carbon::now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $totalExpenses = (float) Expense::where('user_id', $userId)
            ->whereBetween('date', [$start, $end])
            ->sum('amount');

        $totalIncomes = (float) Income::where('user_id', $userId)
            ->whereBetween('date', [$start, $end])
            ->sum('amount');

        $availableBalance = (float) Account::where('user_id', $userId)->sum('balance');
        $totalSavings = (float) Saving::where('user_id', $userId)->sum('current_amount');

        // Tendances vs mois précédent
        $prevStart = $start->copy()->subMonth();
        $prevEnd = $prevStart->copy()->endOfMonth();
        $prevExpenses = (float) Expense::where('user_id', $userId)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->sum('amount');
        $prevIncomes = (float) Income::where('user_id', $userId)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->sum('amount');

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

        $todayExpenses = (float) Expense::where('user_id', $userId)
            ->whereBetween('date', [$todayStart, $todayEnd])
            ->sum('amount');
        $todayIncomes = (float) Income::where('user_id', $userId)
            ->whereBetween('date', [$todayStart, $todayEnd])
            ->sum('amount');
        $weekExpenses = (float) Expense::where('user_id', $userId)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->sum('amount');
        $weekIncomes = (float) Income::where('user_id', $userId)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->sum('amount');

        return response()->json([
            'total_expenses' => $totalExpenses,
            'total_incomes' => $totalIncomes,
            'available_balance' => $availableBalance,
            'total_savings' => $totalSavings,
            'expense_trend' => $expenseTrend,
            'income_trend' => $incomeTrend,
            'savings_rate' => $savingsRate,
            'burn_rate' => $totalExpenses > 0 ? round($totalExpenses / Carbon::now()->day, 2) : 0,
            'today_expenses' => $todayExpenses,
            'today_incomes' => $todayIncomes,
            'week_expenses' => $weekExpenses,
            'week_incomes' => $weekIncomes,
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
