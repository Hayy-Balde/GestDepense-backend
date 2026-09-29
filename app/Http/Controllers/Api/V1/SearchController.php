<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Caisse;
use App\Models\Expense;
use App\Models\Income;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Recherche globale : dépenses, revenus, comptes et caisses de l'utilisateur.
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $userId = $request->user()->id;

        if (mb_strlen($q) < 2) {
            return response()->json(['expenses' => [], 'incomes' => [], 'accounts' => [], 'caisses' => []]);
        }

        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);

        $searchTerm = '%' . $q . '%';

        $expenses = Expense::with('category')
            ->where('user_id', $userId)
            ->forMonth($month, $year)
            ->where(fn ($query) => $query->where('title', 'like', $searchTerm)->orWhere('description', 'like', $searchTerm))
            ->orderBy('date', 'desc')
            ->limit(8)
            ->get()
            ->map(fn (Expense $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'amount' => (float) $e->amount,
                'currency_code' => $e->currency_code,
                'date' => $e->date?->format('Y-m-d'),
                'category' => $e->category?->name,
            ]);

        $incomes = Income::with('category')
            ->where('user_id', $userId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->where('title', 'like', $searchTerm)
            ->orderBy('date', 'desc')
            ->limit(8)
            ->get()
            ->map(fn (Income $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'amount' => (float) $i->amount,
                'currency_code' => $i->currency_code,
                'date' => $i->date?->format('Y-m-d'),
                'category' => $i->category?->name,
            ]);

        $accounts = Account::where('user_id', $userId)
            ->where('name', 'like', $searchTerm)
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type?->value ?? $a->type,
                'currency_code' => $a->currency_code,
                'balance' => (float) $a->balance,
            ]);

        $caisses = Caisse::where('user_id', $userId)
            ->where('name', 'like', $searchTerm)
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Caisse $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'status' => $c->status,
                'remaining' => round((float) $c->budget_amount - (float) $c->spent_amount, 2),
            ]);

        return response()->json([
            'expenses' => $expenses->values(),
            'incomes' => $incomes->values(),
            'accounts' => $accounts->values(),
            'caisses' => $caisses->values(),
        ]);
    }
}
