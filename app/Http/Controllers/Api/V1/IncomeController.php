<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Services\IncomeService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    public function __construct(
        protected IncomeService $incomeService
    ) {}

    public function index(Request $request)
    {
        $incomes = Income::where('user_id', $request->user()->id);

        if ($request->has('month') && $request->has('year')) {
            $incomes->whereYear('date', (int) $request->year)->whereMonth('date', (int) $request->month);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $incomes->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        return response()->json($incomes->latest('date')->paginate(15));
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
            'payment_method' => ['sometimes', 'nullable', 'string', \Illuminate\Validation\Rule::in(['cash', 'bank_transfer', 'mobile_money', 'credit_card', 'debit_card', 'check', 'other'])],
            'is_recurring' => 'sometimes|boolean',
            'recurrence_rule' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
        ]);
        
        $validated['user_id'] = $request->user()->id;

        $income = $this->incomeService->createIncome($validated);
        return response()->json([
            'message' => 'Revenu enregistré avec succès.',
            'income' => $income,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $income = Income::where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json($income);
    }

    public function update(Request $request, $id)
    {
        $income = Income::where('user_id', $request->user()->id)->findOrFail($id);
        $income = $this->incomeService->updateIncome($income, $request->all());
        return response()->json([
            'message' => 'Revenu mis à jour avec succès.',
            'income' => $income,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $income = Income::where('user_id', $request->user()->id)->findOrFail($id);
        $this->incomeService->deleteIncome($income);

        return response()->json(['message' => 'Revenu supprimé avec succès.']);
    }
}