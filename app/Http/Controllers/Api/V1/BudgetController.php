<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $budgets = Budget::where('user_id', $request->user()->id)
                         ->orderBy('year', 'desc')
                         ->orderBy('month', 'desc')
                         ->get();
        return response()->json($budgets);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
            'total_budget' => 'required|numeric',
            'notes' => 'nullable|string',
        ]);
        
        $validated['user_id'] = $request->user()->id;
        
        // Update or Create
        $budget = Budget::updateOrCreate(
            ['user_id' => $validated['user_id'], 'month' => $validated['month'], 'year' => $validated['year']],
            ['total_budget' => $validated['total_budget'], 'notes' => $validated['notes'] ?? null]
        );
        
        return response()->json($budget, 201);
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
