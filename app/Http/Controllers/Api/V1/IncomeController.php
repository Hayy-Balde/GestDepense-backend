<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\DefinesEntryRules;
use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Services\IncomeService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    use DefinesEntryRules;

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
        $validated = $request->validate($this->incomeRules(false));

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
        $income = $this->incomeService->updateIncome($income, $request->validate($this->incomeRules(true)));
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