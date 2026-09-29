<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Movement;
use App\Services\Wallet\MoneyTarget;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(private readonly WalletService $wallet) {}

    public function index(Request $request)
    {
        $invoices = Invoice::where('user_id', $request->user()->id)
            ->with('account:id,name,currency_code')
            ->with('caisse:id,name,currency_code')
            ->with('payments.account:id,name,currency_code')
            ->with('payments.caisse:id,name,currency_code')
            ->orderByDesc('issue_date')
            ->get();
        return response()->json($invoices);
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'direction' => 'required|in:issued,received',
            'number' => 'nullable|string|max:100',
            'counterparty' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency_code' => 'required|string|size:3',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'account_id' => ['nullable', 'uuid', Rule::exists('accounts', 'id')->where('user_id', $userId)],
            'caisse_id' => ['nullable', 'uuid', Rule::exists('caisses', 'id')->where('user_id', $userId)],
        ]);

        if (! empty($validated['account_id']) && ! empty($validated['caisse_id'])) {
            throw ValidationException::withMessages([
                'account_id' => ['Choisissez soit un compte, soit une caisse, pas les deux.'],
            ]);
        }

        // Une facture émise est encaissée sur un compte : une caisse ne peut pas
        // recevoir d'argent, la lier serait trompeur.
        if ($validated['direction'] === 'issued' && ! empty($validated['caisse_id'])) {
            throw ValidationException::withMessages([
                'caisse_id' => ["Une facture émise s'encaisse sur un compte, pas depuis une caisse."],
            ]);
        }

        $validated['user_id'] = $userId;
        $validated['remaining_amount'] = $validated['amount'];
        $validated['status'] = 'pending';

        $invoice = Invoice::create($validated);

        return response()->json($invoice->load('account:id,name,currency_code'), 201);
    }

    public function update(Request $request, $id)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'direction' => 'sometimes|in:issued,received',
            'number' => 'nullable|string|max:100',
            'counterparty' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0.01',
            'currency_code' => 'sometimes|string|size:3',
            'issue_date' => 'sometimes|date',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'account_id' => ['nullable', 'uuid', Rule::exists('accounts', 'id')->where('user_id', $userId)],
            'caisse_id' => ['nullable', 'uuid', Rule::exists('caisses', 'id')->where('user_id', $userId)],
        ]);

        if (! empty($validated['account_id']) && ! empty($validated['caisse_id'])) {
            throw ValidationException::withMessages([
                'account_id' => ['Choisissez soit un compte, soit une caisse, pas les deux.'],
            ]);
        }

        return DB::transaction(function () use ($request, $id, $validated) {
            $invoice = Invoice::where('user_id', $userId)
                ->with(['account', 'caisse', 'payments'])
                ->findOrFail($id);

            $paid = (float) $invoice->payments->sum('amount');

            $invoice->fill($validated);

            // La direction ou la caisse peuvent changer : on revalide la
            // cohérence caisse/interdit d'encaissement.
            $direction = $validated['direction'] ?? $invoice->direction;
            $caisseId = array_key_exists('caisse_id', $validated)
                ? $validated['caisse_id']
                : $invoice->caisse_id;
            if ($direction === 'issued' && ! empty($caisseId)) {
                throw ValidationException::withMessages([
                    'caisse_id' => ["Une facture émise s'encaisse sur un compte, pas depuis une caisse."],
                ]);
            }

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
                $invoice->fresh()->load(
                    'account:id,name,currency_code',
                    'caisse:id,name,currency_code',
                    'payments.account:id,name,currency_code',
                    'payments.caisse:id,name,currency_code',
                )
            );
        });
    }

    public function payment(Request $request, $id)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'note' => 'nullable|string',
            'account_id' => ['required_without:caisse_id', 'nullable', 'uuid', Rule::exists('accounts', 'id')->where('user_id', $userId)],
            'caisse_id' => ['required_without:account_id', 'nullable', 'uuid', Rule::exists('caisses', 'id')->where('user_id', $userId)],
        ]);

        return DB::transaction(function () use ($request, $id, $validated) {
            $userId = $request->user()->id;
            $invoice = Invoice::where('user_id', $userId)->with('account')->findOrFail($id);

            if ($validated['amount'] > (float) $invoice->remaining_amount) {
                throw ValidationException::withMessages([
                    'amount' => ['Le paiement dépasse le montant restant dû.'],
                ]);
            }

            // issued: le client nous paie -> l'argent entre (compte obligatoire).
            // received: nous payons le fournisseur -> l'argent sort (caisse possible).
            $target = $this->wallet->resolve(
                $userId,
                $validated['account_id'] ?? null,
                $validated['caisse_id'] ?? null,
                inbound: $invoice->direction === 'issued',
            );

            if (! $target) {
                throw ValidationException::withMessages([
                    'account_id' => ['Choisissez un compte ou une caisse.'],
                ]);
            }

            $amount = $this->wallet->toTargetCurrency(
                (float) $validated['amount'],
                $invoice->currency_code ?? $target->currencyCode,
                $target,
            );

            if ($invoice->direction === 'issued') {
                $this->wallet->credit($target, $amount);
            } else {
                $this->wallet->debit($target, $amount);
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
                'account_id' => $target->type === MoneyTarget::ACCOUNT ? $target->id : null,
                'caisse_id' => $target->type === MoneyTarget::CAISSE ? $target->id : null,
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
            ]);

            $this->journalPayment($invoice, $payment, $target, $amount);

            return response()->json(
                $invoice->fresh()->load(
                    'account:id,name,currency_code',
                    'caisse:id,name,currency_code',
                    'payments.account:id,name,currency_code',
                    'payments.caisse:id,name,currency_code',
                )
            );
        });
    }

    public function destroy(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $userId = $request->user()->id;
            $invoice = Invoice::where('user_id', $userId)
                ->with(['account', 'caisse', 'payments.account', 'payments.caisse'])
                ->findOrFail($id);

            foreach ($invoice->payments as $payment) {
                $this->reversePaymentEffect($invoice, $payment, $userId);
            }

            // Le journal suit la facture : sans purge, les mouvements resteraient orphelins.
            $paymentIds = $invoice->payments->pluck('id');
            if ($paymentIds->isNotEmpty()) {
                Movement::where('related_type', 'invoice_payment')
                    ->whereIn('related_id', $paymentIds)
                    ->delete();
            }

            $invoice->payments()->delete();
            $invoice->delete();

            return response()->json(null, 204);
        });
    }

    protected function journalPayment(Invoice $invoice, InvoicePayment $payment, MoneyTarget $target, float $amount): void
    {
        $invoiceRef = $invoice->number ?: $invoice->counterparty;
        $inbound = $invoice->direction === 'issued';

        $this->wallet->journal(
            target: $target,
            direction: $inbound ? 'in' : 'out',
            amount: $amount,
            label: $inbound
                ? "Encaissement de la facture « {$invoiceRef} » (client : {$invoice->counterparty})"
                : "Paiement de la facture « {$invoiceRef} » (fournisseur : {$invoice->counterparty})",
            date: $payment->date?->format('Y-m-d'),
            relatedType: 'invoice_payment',
            relatedId: $payment->id,
        );
    }

    protected function reversePaymentEffect(Invoice $invoice, InvoicePayment $payment, string $userId): void
    {
        try {
            $target = $this->wallet->resolve(
                $userId,
                $payment->account_id,
                $payment->caisse_id,
                inbound: $invoice->direction === 'issued',
            );
        } catch (ValidationException|BusinessException) {
            return;
        }

        if (! $target) {
            return;
        }

        $amount = $this->wallet->toTargetCurrency(
            (float) $payment->amount,
            $invoice->currency_code ?? $target->currencyCode,
            $target,
        );

        if ($invoice->direction === 'issued') {
            // L'encaissement est annulé : l'argent repart, même si le solde a
            // été dépensé depuis (sinon la suppression serait impossible).
            $this->wallet->forceDebit($target, $amount);
        } else {
            // Le paiement fournisseur est annulé : il revient dans la cible.
            $this->wallet->refund($target, $amount);
        }
    }
}
