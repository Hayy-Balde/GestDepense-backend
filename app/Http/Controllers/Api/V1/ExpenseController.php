<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
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
        $filters = $request->only(['category_id', 'account_id', 'start_date', 'end_date', 'month', 'year']);
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
            'caisse_id' => 'nullable|uuid|exists:caisses,id',
        ]);
        
        // Add user_id automatically
        $validated['user_id'] = $request->user()->id ?? null;

        $expense = $this->expenseService->createExpense($validated);
        return response()->json([
            'message' => 'Dépense enregistrée avec succès.',
            'expense' => $expense,
        ], 201);
    }

    public function show($id) 
    { 
        return response()->json($this->expenseRepository->getById($id)); 
    }

    public function update(Request $request, $id) 
    { 
        $expense = $this->expenseService->updateExpense($id, $request->all());
        return response()->json([
            'message' => 'Dépense mise à jour avec succès.',
            'expense' => $expense,
        ]); 
    }

    public function destroy($id) 
    { 
        $this->expenseService->deleteExpense($id);
        return response()->json(['message' => 'Dépense supprimée avec succès.']); 
    }

    public function export(Request $request) 
    { 
        $expenses = Expense::where('user_id', $request->user()->id)
            ->with(['account', 'category'])
            ->orderBy('date', 'desc')
            ->get();

        $filename = 'expenses-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($expenses) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Date', 'Titre', 'Montant', 'Devise', 'Compte', 'Catégorie', 'Mode de paiement', 'Statut']);

            foreach ($expenses as $expense) {
                fputcsv($handle, [
                    $expense->date?->format('Y-m-d'),
                    $expense->title,
                    $expense->amount,
                    $expense->currency_code,
                    $expense->account?->name,
                    $expense->category?->name,
                    $expense->payment_method?->value ?? $expense->payment_method,
                    $expense->status?->value ?? $expense->status,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
