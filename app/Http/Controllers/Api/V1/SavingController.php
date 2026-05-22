<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Saving;
use App\Models\SavingTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SavingController extends Controller
{
    public function index(Request $request)
    {
        $savings = Saving::where('user_id', $request->user()->id)->get();
        return response()->json($savings);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'target_amount' => 'required|numeric',
            'deadline' => 'nullable|date',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
        ]);
        
        $validated['user_id'] = $request->user()->id;
        $validated['current_amount'] = 0;
        
        $saving = Saving::create($validated);
        return response()->json($saving, 201);
    }

    public function deposit(Request $request, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $id, $validated) {
            $saving = Saving::where('user_id', $request->user()->id)->findOrFail($id);
            
            $saving->current_amount += $validated['amount'];
            $saving->save();

            SavingTransaction::create([
                'saving_id' => $saving->id,
                'type' => 'deposit',
                'amount' => $validated['amount'],
                'date' => now(),
                'note' => $validated['note'] ?? null,
            ]);

            return response()->json($saving);
        });
    }

    public function withdraw(Request $request, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $id, $validated) {
            $saving = Saving::where('user_id', $request->user()->id)->findOrFail($id);
            
            $saving->current_amount -= $validated['amount'];
            $saving->save();

            SavingTransaction::create([
                'saving_id' => $saving->id,
                'type' => 'withdrawal',
                'amount' => $validated['amount'],
                'date' => now(),
                'note' => $validated['note'] ?? null,
            ]);

            return response()->json($saving);
        });
    }
}
