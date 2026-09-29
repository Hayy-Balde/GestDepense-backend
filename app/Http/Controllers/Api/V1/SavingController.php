<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Saving;
use App\Models\SavingTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavingController extends Controller
{
    public function index(Request $request)
    {
        $savings = Saving::where('user_id', $request->user()->id)
            ->with('account:id,name,currency_code')
            ->get();
        return response()->json($savings);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'target_amount' => 'required|numeric|min:0',
            'deadline' => 'nullable|date',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
            'account_id' => 'required|uuid|exists:accounts,id',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['current_amount'] = 0;

        $saving = Saving::create($validated);
        return response()->json($saving->load('account:id,name,currency_code'), 201);
    }

    public function update(Request $request, $id)
    {
        $saving = Saving::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'target_amount' => 'sometimes|numeric|min:0',
            'deadline' => 'nullable|date',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
            'account_id' => 'sometimes|uuid|exists:accounts,id',
        ]);

        $saving->update($validated);
        return response()->json($saving->load('account:id,name,currency_code'));
    }

    public function destroy(Request $request, $id)
    {
        $saving = Saving::where('user_id', $request->user()->id)->findOrFail($id);
        $saving->delete();
        return response()->json(null, 204);
    }

    public function deposit(Request $request, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
            'source_account_id' => 'sometimes|uuid|exists:accounts,id',
        ]);

        return DB::transaction(function () use ($request, $id, $validated) {
            $saving = Saving::where('user_id', $request->user()->id)->with('account')->findOrFail($id);

            $accountId = $validated['source_account_id'] ?? $saving->account_id;

            if (! $accountId) {
                throw ValidationException::withMessages([
                    'source_account_id' => ["Aucun compte lié à cet objectif d'épargne."],
                ]);
            }

            $account = Account::where('user_id', $request->user()->id)->findOrFail($accountId);
            $amountInAccountCurrency = (float) $validated['amount'];

            if ((float) $account->balance < $amountInAccountCurrency) {
                throw ValidationException::withMessages([
                    'amount' => ["Solde insuffisant sur le compte {$account->name}."],
                ]);
            }

            $account->decrement('balance', $amountInAccountCurrency);
            $saving->increment('current_amount', (float) $validated['amount']);

            if ((float) $saving->current_amount >= (float) $saving->target_amount) {
                $saving->update(['status' => 'completed']);
            }

            SavingTransaction::create([
                'saving_id' => $saving->id,
                'type' => 'deposit',
                'amount' => $validated['amount'],
                'date' => now(),
                'note' => $validated['note'] ?? null,
            ]);

            return response()->json($saving->fresh()->load('account:id,name,currency_code'));
        });
    }

    public function withdraw(Request $request, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
            'source_account_id' => 'sometimes|uuid|exists:accounts,id',
        ]);

        return DB::transaction(function () use ($request, $id, $validated) {
            $saving = Saving::where('user_id', $request->user()->id)->with('account')->findOrFail($id);

            $accountId = $validated['source_account_id'] ?? $saving->account_id;

            if (! $accountId) {
                throw ValidationException::withMessages([
                    'source_account_id' => ["Aucun compte lié à cet objectif d'épargne."],
                ]);
            }

            if ((float) $validated['amount'] > (float) $saving->current_amount) {
                throw ValidationException::withMessages([
                    'amount' => ["Le montant du retrait dépasse le solde de l'épargne."],
                ]);
            }

            $account = Account::where('user_id', $request->user()->id)->findOrFail($accountId);
            $amountInAccountCurrency = (float) $validated['amount'];

            $account->increment('balance', $amountInAccountCurrency);
            $saving->decrement('current_amount', (float) $validated['amount']);

            if ($saving->status === 'completed' && (float) $saving->current_amount < (float) $saving->target_amount) {
                $saving->update(['status' => 'active']);
            }

            SavingTransaction::create([
                'saving_id' => $saving->id,
                'type' => 'withdrawal',
                'amount' => $validated['amount'],
                'date' => now(),
                'note' => $validated['note'] ?? null,
            ]);

            return response()->json($saving->fresh()->load('account:id,name,currency_code'));
        });
    }
}
