<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\DebtPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DebtController extends Controller
{
    public function index(Request $request)
    {
        $debts = Debt::where('user_id', $request->user()->id)->get();
        return response()->json($debts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:lent,borrowed',
            'person_name' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);
        
        $validated['user_id'] = $request->user()->id;
        $validated['remaining_amount'] = $validated['amount'];
        $validated['status'] = 'pending';
        
        $debt = Debt::create($validated);
        return response()->json($debt, 201);
    }

    public function payment(Request $request, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $id, $validated) {
            $debt = Debt::where('user_id', $request->user()->id)->findOrFail($id);
            
            $debt->remaining_amount -= $validated['amount'];
            if ($debt->remaining_amount <= 0) {
                $debt->status = 'paid';
            }
            $debt->save();

            DebtPayment::create([
                'debt_id' => $debt->id,
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
            ]);

            return response()->json($debt);
        });
    }
}
