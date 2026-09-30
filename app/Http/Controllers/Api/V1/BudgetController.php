<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ValidatesTargets;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\Category;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BudgetController extends Controller
{
    use ValidatesTargets;

    public function index(Request $request)
    {
        if ($request->has('month') && $request->has('year')) {
            $budget = Budget::where('user_id', $request->user()->id)
                            ->where('month', $request->integer('month'))
                            ->where('year', $request->integer('year'))
                            ->first();

            return response()->json($budget);
        }

        $budgets = Budget::where('user_id', $request->user()->id)
                        ->orderBy('year', 'desc')
                        ->orderBy('month', 'desc')
                        ->get();

        return response()->json($budgets);
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
            'total_budget' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'categories' => 'required|array',
            'categories.*.category_id' => ['required', 'uuid', $this->ownedOrSystemCategory()],
            // `spent_amount` est une donnée calculée à partir des dépenses
            // réelles (voir `recalculate` plus bas) : l'accepter du client
            // permettait d'afficher un budget faux sans créer de dépense.
            'categories.*.allocated_amount' => 'required|numeric|min:0',
        ]);

        try{
            DB::beginTransaction();

            // Update or Create
            $budget = Budget::updateOrCreate(
                ['user_id' => $userId, 'month' => $validated['month'], 'year' => $validated['year']],
                ['total_budget' => $validated['total_budget'], 'notes' => $validated['notes'] ?? null]
            );

            foreach ($validated['categories'] as $categorie) {
                BudgetCategory::updateOrCreate(
                    [
                        'budget_id' => $budget->id,
                        'category_id' => $categorie['category_id'],
                    ],
                    [
                        'allocated_amount' => $categorie['allocated_amount'],
                    ]
                );
            }

            DB::commit();

            return response()->json($budget->load('categories.category'), 201);
        } catch(\Throwable $th){
            DB::rollBack();
            return response()->json("Une erreur est survenue : ". $th->getMessage(), 500);

        }
    }

    public function byCategory(Request $request)
    {
        $userId = $request->user()->id;
        $month = (int) $request->query('month', Carbon::now()->month);
        $year = (int) $request->query('year', Carbon::now()->year);

        $budget = Budget::where('user_id', $userId)
                        ->where('month', $month)
                        ->where('year', $year)
                        ->first();

        $allocations = $budget
            ? $budget->categories->keyBy('category_id')
            : collect();

        // Dépenses réelles du mois, agrégées par catégorie
        $spentRows = Expense::where('user_id', $userId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->selectRaw('category_id, sum(amount) as total, count(*) as cnt')
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        $categories = Category::where('type', 'expense')
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            })
            ->orderBy('name')
            ->get();

        $rows = $categories->map(function ($cat) use ($allocations, $spentRows) {
            $alloc = $allocations->get($cat->id);
            $spent = $spentRows->get($cat->id);

            $allocated = (float) ($alloc->allocated_amount ?? 0);
            $spentAmount = (float) ($spent->total ?? 0);

            return [
                'category' => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'color' => $cat->color ?? '#6B7280',
                    'icon' => $cat->icon ?? 'more-horizontal',
                    'is_system' => (bool) $cat->is_system,
                ],
                'allocated_amount' => $allocated,
                'spent_amount' => $spentAmount,
                'remaining' => round($allocated - $spentAmount, 2),
                'percentage_used' => $allocated > 0
                    ? round(($spentAmount / $allocated) * 100, 1)
                    : 0,
                'transaction_count' => (int) ($spent->cnt ?? 0),
                'is_planned' => $allocated > 0,
            ];
        });

        $totalAllocated = $rows->sum('allocated_amount');
        $totalSpent = $rows->sum('spent_amount');

        return response()->json([
            'month' => $month,
            'year' => $year,
            'has_budget' => (bool) $budget,
            'total_allocated' => round($totalAllocated, 2),
            'total_spent' => round($totalSpent, 2),
            'total_remaining' => round($totalAllocated - $totalSpent, 2),
            'categories' => $rows->values(),
        ]);
    }

    public function getByMonth(Request $request, $month, $year)
    {
        $budget = Budget::where('user_id', $request->user()->id)
                        ->where('month', $month)
                        ->where('year', $year)
                        ->first();
                        
        if (!$budget) {
            return response()->json(['message' => 'Budget not found for this month'], 404);
        }
        
        return response()->json($budget);
    }
}
