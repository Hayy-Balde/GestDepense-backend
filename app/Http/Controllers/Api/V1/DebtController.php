<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\DebtPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DebtController extends Controller
{
    public function index(Request $request)
    {
        $debts = Debt::where('user_id', $request->user()->id)
            ->with('account:id,name,currency_code')
            ->with('payments.account:id,name,currency_code')
            ->get();
        return response()->json($debts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:lent,borrowed',
            'person_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'account_id' => 'required|uuid|exists:accounts,id',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['remaining_amount'] = $validated['amount'];
        $validated['status'] = 'pending';

        $debt = DB::transaction(function () use ($validated) {
            $debt = Debt::create($validated);

            $this->applyCreationEffect($debt);

            return $debt;
        });

        return response()->json($debt->load('account:id,name,currency_code'), 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'type' => 'sometimes|in:lent,borrowed',
            'person_name' => 'sometimes|string|max:255',
            'person_contact' => 'nullable|string|max:255',
            'amount' => 'sometimes|numeric|min:0.01',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'account_id' => 'sometimes|uuid|exists:accounts,id',
        ]);

        return DB::transaction(function () use ($request, $id, $validated) {
            $debt = Debt::where('user_id', $request->user()->id)
                ->with(['account', 'payments'])
                ->findOrFail($id);

            $oldAccount = $debt->account;
            $paid = (float) $debt->payments->sum('amount');

            // Revert the creation effect before changing anything
            $this->reverseCreationEffect($debt);

            $debt->fill($validated);

            if (isset($validated['amount'])) {
                $debt->remaining_amount = max(0, (float) $validated['amount'] - $paid);
            }

            if ((float) $debt->remaining_amount <= 0) {
                $debt->status = 'paid';
            } elseif ($debt->status === 'paid') {
                $debt->status = 'partially_paid';
            }

            $debt->save();

            // Apply the new creation effect (payments are untouched)
            $newAccount = $debt->account_id === $oldAccount?->id
                ? $oldAccount
                : \App\Models\Account::find($debt->account_id);

            if ($newAccount) {
                $debt->setRelation('account', $newAccount);
                $this->applyCreationEffect($debt);
            }

            return response()->json($debt->fresh()->load('account:id,name,currency_code', 'payments.account:id,name,currency_code'));
        });
    }

    public function payment(Request $request, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'note' => 'nullable|string',
            'account_id' => 'required|uuid|exists:accounts,id',
        ]);

        return DB::transaction(function () use ($request, $id, $validated) {
            $debt = Debt::where('user_id', $request->user()->id)->with('account')->findOrFail($id);

            if ($validated['amount'] > (float) $debt->remaining_amount) {
                throw ValidationException::withMessages([
                    'amount' => ['Le paiement dépasse le montant restant dû.'],
                ]);
            }

            $account = \App\Models\Account::where('user_id', $request->user()->id)
                ->where('id', $validated['account_id'])
                ->first();

            if (! $account) {
                throw ValidationException::withMessages([
                    'account_id' => ['Compte invalide.'],
                ]);
            }

            // lent: repayment received -> credit the chosen account
            // borrowed: repayment paid -> debit the chosen account
            if ($debt->type === 'lent') {
                $account->increment('balance', (float) $validated['amount']);
            } else {
                $account->decrement('balance', (float) $validated['amount']);
            }

            $debt->remaining_amount -= $validated['amount'];
            if ($debt->remaining_amount <= 0) {
                $debt->status = 'paid';
            }
            $debt->save();

            DebtPayment::create([
                'debt_id' => $debt->id,
                'account_id' => $account->id,
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
            ]);

            return response()->json($debt->fresh()->load('account:id,name,currency_code', 'payments.account:id,name,currency_code'));
        });
    }

    public function destroy(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $debt = Debt::where('user_id', $request->user()->id)
                ->with(['account', 'payments.account'])
                ->findOrFail($id);

            $this->reverseCreationEffect($debt);

            foreach ($debt->payments as $payment) {
                $this->reversePaymentEffect($debt, $payment);
            }

            $debt->payments()->delete();
            $debt->delete();

            return response()->json(null, 204);
        });
    }

    protected function applyCreationEffect(Debt $debt): void
    {
        $account = $debt->account;
        if (! $account) return;

        if ($debt->type === 'lent') {
            // we lend money -> balance decreases
            $account->decrement('balance', (float) $debt->amount);
        } else {
            // we borrow money -> balance increases
            $account->increment('balance', (float) $debt->amount);
        }
    }

    protected function reverseCreationEffect(Debt $debt): void
    {
        $account = $debt->account;
        if (! $account) return;

        if ($debt->type === 'lent') {
            $account->increment('balance', (float) $debt->amount);
        } else {
            $account->decrement('balance', (float) $debt->amount);
        }
    }

    protected function reversePaymentEffect(Debt $debt, DebtPayment $payment): void
    {
        $account = $payment->account;
        if (! $account) return;

        if ($debt->type === 'lent') {
            $account->decrement('balance', (float) $payment->amount);
        } else {
            $account->increment('balance', (float) $payment->amount);
        }
    }
}