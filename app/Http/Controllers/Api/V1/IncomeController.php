<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Income;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    public function index(Request $request)
    {
        $incomes = Income::where('user_id', $request->user()->id)->paginate();
        return response()->json($incomes);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'title' => 'required|string|max:255',
            'account_id' => 'required|uuid|exists:accounts,id',
            'category_id' => 'required|uuid|exists:categories,id',
            'date' => 'required|date',
            'currency_code' => 'required|string|size:3',
        ]);
        
        $validated['user_id'] = $request->user()->id;

        return \Illuminate\Support\Facades\DB::transaction(function () use ($validated) {
            $income = Income::create($validated);
            $account = \App\Models\Account::find($validated['account_id']);
            $account->balance += $income->amount;
            $account->save();
            return response()->json($income, 201);
        });
    }

    public function show(Request $request, $id)
    {
        $income = Income::where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json($income);
    }

    public function update(Request $request, $id)
    {
        $income = Income::where('user_id', $request->user()->id)->findOrFail($id);
        $income->update($request->all());
        return response()->json($income);
    }

    public function destroy(Request $request, $id)
    {
        $income = Income::where('user_id', $request->user()->id)->findOrFail($id);
        
        \Illuminate\Support\Facades\DB::transaction(function () use ($income) {
            if ($income->account_id) {
                $account = \App\Models\Account::find($income->account_id);
                $account->balance -= $income->amount;
                $account->save();
            }
            $income->delete();
        });

        return response()->json(null, 204);
    }
}
