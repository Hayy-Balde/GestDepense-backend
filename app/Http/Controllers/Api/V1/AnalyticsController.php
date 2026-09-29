<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Category;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function trends(Request $request)
    {
        $userId = $request->user()->id;
        $period = $request->query('period', '6m');
        $anchorYear = (int) $request->query('year', Carbon::now()->year);
        $anchorMonth = (int) $request->query('month', Carbon::now()->month);

        $daily = in_array($period, ['7d', '30d'], true);

        if ($daily) {
            $end = Carbon::now();
            [$start] = match ($period) {
                '7d' => [$end->copy()->subDays(6)->startOfDay()],
                default => [$end->copy()->subDays(29)->startOfDay()],
            };
        } else {
            $end = Carbon::create($anchorYear, $anchorMonth, 1)->endOfMonth();
            [$start] = match ($period) {
                '90d' => [$end->copy()->subMonths(2)->startOfMonth()],
                '365d' => [$end->copy()->subMonths(11)->startOfMonth()],
                default => [$end->copy()->subMonths(5)->startOfMonth()],
            };
        }

        // Ordered buckets
        $buckets = [];
        if ($daily) {
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $buckets[$d->format('Y-m-d')] = ['label' => $d->format('d/m'), 'incomes' => 0, 'expenses' => 0];
            }
            $day = fn ($date) => $date->format('Y-m-d');
        } else {
            for ($m = $start->copy(); $m->lte($end); $m->addMonth()) {
                $buckets[$m->format('Y-m')] = ['label' => $m->format('M'), 'incomes' => 0, 'expenses' => 0];
            }
            $day = fn ($date) => $date->format('Y-m');
        }

        foreach (Income::where('user_id', $userId)->whereBetween('date', [$start, $end])->get(['date', 'amount']) as $row) {
            $key = $day($row->date);
            if (isset($buckets[$key])) $buckets[$key]['incomes'] += (float) $row->amount;
        }
        foreach (Expense::where('user_id', $userId)->whereBetween('date', [$start, $end])->get(['date', 'amount']) as $row) {
            $key = $day($row->date);
            if (isset($buckets[$key])) $buckets[$key]['expenses'] += (float) $row->amount;
        }

        $trends = array_values(array_map(fn ($b) => [
            'month' => $b['label'],
            'incomes' => $b['incomes'],
            'expenses' => $b['expenses'],
            'savings' => max(0, $b['incomes'] - $b['expenses']),
        ], $buckets));

        return response()->json(['data' => $trends]);
    }

    public function breakdown(Request $request)
    {
        $userId = $request->user()->id;
        $type = $request->query('type', 'expense');
        $month = (int) $request->query('month', Carbon::now()->month);
        $year = (int) $request->query('year', Carbon::now()->year);

        if ($type === 'income') {
            $rows = Income::where('user_id', $userId)
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->selectRaw('category_id, sum(amount) as total, count(*) as cnt')
                ->groupBy('category_id')
                ->get();
        } else {
            $rows = Expense::where('user_id', $userId)
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->selectRaw('category_id, sum(amount) as total, count(*) as cnt')
                ->groupBy('category_id')
                ->get();
        }

        $grandTotal = (float) $rows->sum('total');
        $categories = Category::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhereNull('user_id');
        })->get()->keyBy('id');

        $breakdown = $rows->map(function ($row) use ($grandTotal, $categories) {
            $cat = $categories->get($row->category_id);
            return [
                'category' => $cat?->name ?? 'Autre',
                'amount' => (float) $row->total,
                'percentage' => $grandTotal > 0 ? round(((float) $row->total / $grandTotal) * 100, 1) : 0,
                'color' => $cat?->color ?? '#6366F1',
                'icon' => $cat?->icon ?? '📦',
                'count' => (int) $row->cnt,
            ];
        });

        return response()->json(['data' => $breakdown->values()]);
    }

    public function monthly(Request $request)
    {
        $userId = $request->user()->id;

        $summary = [];
        // Last 6 months summary
        for ($i = 5; $i >= 0; $i--) {
            $m = Carbon::now()->subMonths($i);

            $incomes = (float) Income::where('user_id', $userId)
                ->whereYear('date', $m->year)->whereMonth('date', $m->month)
                ->sum('amount');
            $expenses = (float) Expense::where('user_id', $userId)
                ->whereYear('date', $m->year)->whereMonth('date', $m->month)
                ->sum('amount');

            $summary[] = [
                'month' => (int) $m->month,
                'year' => (int) $m->year,
                'incomes' => $incomes,
                'expenses' => $expenses,
                'savings' => max(0, $incomes - $expenses),
                'surplus' => $incomes - $expenses,
                'top_categories' => [],
                'daily_spending' => [],
            ];
        }

        return response()->json(['data' => $summary]);
    }
}
