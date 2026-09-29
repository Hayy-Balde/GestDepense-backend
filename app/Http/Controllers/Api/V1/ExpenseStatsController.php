<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ExpenseStatsController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $month = (int) $request->query('month', Carbon::now()->month);
        $year = (int) $request->query('year', Carbon::now()->year);

        $query = Expense::where('expenses.user_id', $userId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        $total = (float) (clone $query)->sum('amount');
        $count = (clone $query)->count();

        $byCategory = (clone $query)
            ->join('categories', 'categories.id', '=', 'expenses.category_id')
            ->selectRaw('categories.id, categories.name, categories.icon, categories.color, sum(expenses.amount) as amount, count(*) as cnt')
            ->groupBy('categories.id', 'categories.name', 'categories.icon', 'categories.color')
            ->get()
            ->map(function ($row) use ($total) {
                return [
                    'category_id' => $row->id,
                    'category' => $row->name,
                    'icon' => $row->icon,
                    'color' => $row->color,
                    'amount' => (float) $row->amount,
                    'count' => (int) $row->cnt,
                    'percentage' => $total > 0 ? round(((float) $row->amount / $total) * 100, 1) : 0,
                ];
            })
            ->values();

        // Catégorie la plus fréquente : celle qui a le plus grand nombre de dépenses
        $mostFrequent = $byCategory->count()
            ? $byCategory->sortByDesc('count')->first()
            : null;

        // Catégorie avec le plus gros montant
        $highestAmount = $byCategory->count()
            ? $byCategory->sortByDesc('amount')->first()
            : null;

        // Mois précédent pour la comparaison
        $lastMonth = Carbon::createFromDate($year, $month, 1)->subMonth();
        $lastMonthTotal = (float) Expense::where('user_id', $userId)
            ->whereYear('date', $lastMonth->year)
            ->whereMonth('date', $lastMonth->month)
            ->sum('amount');
        $lastMonthCount = (int) Expense::where('user_id', $userId)
            ->whereYear('date', $lastMonth->year)
            ->whereMonth('date', $lastMonth->month)
            ->count();

        $vsLastMonth = $lastMonthTotal > 0
            ? round(((($total - $lastMonthTotal) / $lastMonthTotal) * 100), 1)
            : ($total > 0 ? 100 : 0);

        $prevAverage = $lastMonthCount > 0 ? round($lastMonthTotal / $lastMonthCount, 2) : 0;

        return response()->json([
            'total' => $total,
            'count' => $count,
            'average' => $count > 0 ? round($total / $count, 2) : 0,
            'prev_average' => $prevAverage,
            'prev_total' => $lastMonthTotal,
            'vs_last_month' => $vsLastMonth,
            'by_category' => $byCategory,
            'most_frequent_category' => $mostFrequent,
            'highest_amount_category' => $highestAmount,
        ]);
    }
}
