<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Movement;
use App\Services\CurrencyConverter;
use App\Services\MovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(
        protected CurrencyConverter $currencyConverter,
        protected MovementService $movements
    ) {}

    public function index(Request $request)
    {
        $invoices = Invoice::where('user_id', $request->user()->id)
            ->with('account:id,name,currency_code')
            ->with('payments.account:id,name,currency_code')
            ->orderByDesc('issue_date')
            ->get();
        return response()->json($invoices);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'direction' => 'required|in:issued,received',
            'number' => 'nullable|string|max:100',
            'counterparty' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency_code' => 'required|string|size:3',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'account_id' => 'nullable|uuid|exists:accounts,id',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['remaining_amount'] = $validated['amount'];
        $validated['status'] = 'pending';

        $invoice = Invoice::create($validated);

        return response()->json($invoice->load('account:id,name,currency_code'), 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'direction' => 'sometimes|in:issued,received',
            'number' => 'nullable|string|max:100',
            'counterparty' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0.01',
            'currency_code' => 'sometimes|string|size:3',
            'issue_date' => 'sometimes|date',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'account_id' => 'nullable|uuid|exists:accounts,id',
        ]);

        return DB::transaction(function () use ($request, $id, $validated) {
            $invoice = Invoice::where('user_id', $request->user()->id)
                ->with(['account', 'payments'])
                ->findOrFail($id);

            $paid = (float) $invoice->payments->sum('amount');

            $invoice->fill($validated);

            if (isset($validated['amount'])) {
                $invoice->remaining_amount = max(0, (float) $validated['amount'] - $paid);
            }

            if ((float) $invoice->remaining_amount <= 0) {
                $invoice->status = 'paid';
            } elseif ($invoice->status === 'paid') {
                $invoice->status = 'partially_paid';
            }

            $invoice->save();

            return response()->json(
                $invoice->fresh()->load('account:id,name,currency_code', 'payments.account:id,name,currency_code')
            );
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
            $invoice = Invoice::where('user_id', $request->user()->id)->with('account')->findOrFail($id);

            if ($validated['amount'] > (float) $invoice->remaining_amount) {
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

            // issued: the client pays us -> credit the chosen account
            // received: we pay the supplier -> debit the chosen account
            $amount = $this->currencyConverter->convert(
                (float) $validated['amount'],
                $invoice->currency_code,
                $account->currency_code
            );

            if ($invoice->direction === 'received' && (float) $account->balance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => ["Solde insuffisant sur le compte {$account->name}."],
                ]);
            }

            if ($invoice->direction === 'issued') {
                $account->increment('balance', $amount);
            } else {
                $account->decrement('balance', $amount);
            }

            $invoice->remaining_amount -= $validated['amount'];
            if ($invoice->remaining_amount <= 0) {
                $invoice->status = 'paid';
            } elseif ($invoice->status === 'pending') {
                $invoice->status = 'partially_paid';
            }
            $invoice->save();

            $payment = InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'account_id' => $account->id,
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
            ]);

            $invoiceRef = $invoice->number ?: $invoice->counterparty;
            $this->movements->record([
                'user_id' => $invoice->user_id,
                'type' => $invoice->direction === 'issued' ? Movement::TYPE_INCOME : Movement::TYPE_EXPENSE,
                'from_type' => $invoice->direction === 'issued' ? 'external' : 'account',
                'from_id' => $invoice->direction === 'issued' ? null : $account->id,
                'to_type' => $invoice->direction === 'issued' ? 'account' : 'external',
                'to_id' => $invoice->direction === 'issued' ? $account->id : null,
                'amount' => $amount,
                'currency_code' => $account->currency_code,
                'label' => $invoice->direction === 'issued'
                    ? "Encaissement de la facture « {$invoiceRef} » (client : {$invoice->counterparty})"
                    : "Paiement de la facture « {$invoiceRef} » (fournisseur : {$invoice->counterparty})",
            ]);

            return response()->json(
                $invoice->fresh()->load('account:id,name,currency_code', 'payments.account:id,name,currency_code')
            );
        });
    }

    public function destroy(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $invoice = Invoice::where('user_id', $request->user()->id)
                ->with(['account', 'payments.account'])
                ->findOrFail($id);

            foreach ($invoice->payments as $payment) {
                $this->reversePaymentEffect($invoice, $payment);
            }

            $invoice->payments()->delete();
            $invoice->delete();

            return response()->json(null, 204);
        });
    }

    protected function reversePaymentEffect(Invoice $invoice, InvoicePayment $payment): void
    {
        $account = $payment->account;
        if (! $account) return;

        $amount = $this->currencyConverter->convert(
            (float) $payment->amount,
            $invoice->currency_code,
            $account->currency_code
        );

        if ($invoice->direction === 'issued') {
            $account->decrement('balance', $amount);
        } else {
            $account->increment('balance', $amount);
        }
    }
}
