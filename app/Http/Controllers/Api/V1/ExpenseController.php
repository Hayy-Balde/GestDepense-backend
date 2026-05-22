<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ExpenseService;
use Illuminate\Http\Request;

use App\Repositories\Interfaces\ExpenseRepositoryInterface;

class ExpenseController extends Controller
{
    protected ExpenseService $expenseService;
    protected ExpenseRepositoryInterface $expenseRepository;

    public function __construct(ExpenseService $expenseService, ExpenseRepositoryInterface $expenseRepository)
    {
        $this->expenseService = $expenseService;
        $this->expenseRepository = $expenseRepository;
    }

    public function index(Request $request) 
    { 
        $filters = $request->only(['category_id', 'account_id', 'start_date', 'end_date']);
        return response()->json($this->expenseRepository->getAll($filters)); 
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
        
        // Add user_id automatically
        $validated['user_id'] = $request->user()->id ?? null;

        $expense = $this->expenseService->createExpense($validated);
        return response()->json($expense, 201); 
    }

    public function show($id) 
    { 
        return response()->json($this->expenseRepository->getById($id)); 
    }

    public function update(Request $request, $id) 
    { 
        // Simple update without balance readjustment for now
        $expense = $this->expenseRepository->update($id, $request->all());
        return response()->json($expense); 
    }

    public function destroy($id) 
    { 
        $this->expenseService->deleteExpense($id);
        return response()->json(null, 204); 
    }

    public function export(Request $request) 
    { 
        // Placeholder for export
        return response()->json(['message' => 'Export not implemented yet']); 
    }
}
