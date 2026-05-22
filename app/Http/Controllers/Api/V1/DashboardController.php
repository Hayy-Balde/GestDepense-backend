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
        $currentMonth = Carbon::now()->startOfMonth();

        $totalExpenses = Expense::where('user_id', $userId)
            ->where('date', '>=', $currentMonth)
            ->sum('amount');

        $totalIncomes = Income::where('user_id', $userId)
            ->where('date', '>=', $currentMonth)
            ->sum('amount');

        $availableCash = Account::where('user_id', $userId)->sum('balance');
        $totalSavings = Saving::where('user_id', $userId)->sum('current_amount');

        return response()->json([
            'total_expenses' => $totalExpenses,
            'total_incomes' => $totalIncomes,
            'available_cash' => $availableCash,
            'total_savings' => $totalSavings,
            'burn_rate' => $totalExpenses > 0 ? $totalExpenses / date('j') : 0, // Approx per day
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
        $currentMonth = Carbon::now()->startOfMonth();

        $breakdown = Expense::where('user_id', $userId)
            ->where('date', '>=', $currentMonth)
            ->selectRaw('category_id, sum(amount) as total')
            ->groupBy('category_id')
            ->get();

        return response()->json($breakdown);
    }
}
