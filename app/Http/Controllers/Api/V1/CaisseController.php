<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Caisse;
use Illuminate\Http\Request;

class CaisseController extends Controller
{
    public function index(Request $request)
    {
        $caisses = Caisse::where('user_id', $request->user()->id)->get();
        return response()->json($caisses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'budget_amount' => 'required|numeric',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
            'description' => 'nullable|string',
        ]);
        
        $validated['user_id'] = $request->user()->id;
        $caisse = Caisse::create($validated);
        
        return response()->json($caisse, 201);
    }

    public function show(Request $request, $id)
    {
        $caisse = Caisse::where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json($caisse);
    }

    public function stats(Request $request, $id)
    {
        // Get total expenses linked to this caisse
        $caisse = Caisse::where('user_id', $request->user()->id)->findOrFail($id);
        $totalSpent = \App\Models\Expense::where('caisse_id', $id)->sum('amount');
        
        return response()->json([
            'caisse' => $caisse,
            'total_spent' => $totalSpent,
            'remaining_budget' => $caisse->budget_amount - $totalSpent,
        ]);
    }
}
