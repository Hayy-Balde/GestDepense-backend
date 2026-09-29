<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Income;
use Illuminate\Http\Request;
use Carbon\Carbon;

class IncomeStatsController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $month = (int) $request->query('month', Carbon::now()->month);
        $year = (int) $request->query('year', Carbon::now()->year);

        $query = Income::where('incomes.user_id', $userId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        $total = (float) (clone $query)->sum('amount');
        $count = (clone $query)->count();

        $byCategory = (clone $query)
            ->join('categories', 'categories.id', '=', 'incomes.category_id')
            ->selectRaw('categories.id, categories.name, categories.icon, sum(incomes.amount) as amount')
            ->groupBy('categories.id', 'categories.name', 'categories.icon')
            ->get()
            ->map(function ($row) use ($total) {
                return [
                    'category_id' => $row->id,
                    'category' => $row->name,
                    'icon' => $row->icon,
                    'amount' => (float) $row->amount,
                    'percentage' => $total > 0 ? round(((float) $row->amount / $total) * 100, 1) : 0,
                ];
            })
            ->values();

        // Last month for comparison
        $lastMonth = Carbon::createFromDate($year, $month, 1)->subMonth();
        $lastMonthTotal = (float) Income::where('user_id', $userId)
            ->whereYear('date', $lastMonth->year)
            ->whereMonth('date', $lastMonth->month)
            ->sum('amount');

        $vsLastMonth = $lastMonthTotal > 0
            ? round(((($total - $lastMonthTotal) / $lastMonthTotal) * 100), 1)
            : ($total > 0 ? 100 : 0);

        return response()->json([
            'total' => $total,
            'count' => $count,
            'average' => $count > 0 ? round($total / $count, 2) : 0,
            'by_category' => $byCategory,
            'by_month' => [],
            'vs_last_month' => $vsLastMonth,
        ]);
    }
}
