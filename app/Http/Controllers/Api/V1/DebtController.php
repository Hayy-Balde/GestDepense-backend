<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Exceptions\BusinessException;
use App\Models\Movement;
use App\Services\Wallet\MoneyTarget;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DebtController extends Controller
{
    public function __construct(private readonly WalletService $wallet) {}

    public function index(Request $request)
    {
        $debts = Debt::where('user_id', $request->user()->id)
            ->with('account:id,name,currency_code')
            ->with('caisse:id,name,currency_code')
            ->with('payments.account:id,name,currency_code')
            ->with('payments.caisse:id,name,currency_code')
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($debts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:lent,borrowed',
            'person_name' => 'required|string|max:255',
            'person_contact' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency_code' => 'nullable|string|max:3',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'account_id' => 'required_without:caisse_id|nullable|uuid|exists:accounts,id',
            'caisse_id' => 'required_without:account_id|nullable|uuid|exists:caisses,id',
        ]);

        $userId = $request->user()->id;

        // `lent` sort l'argent (caisse possible), `borrowed` le fait entrer
        // (compte obligatoire, une caisse ne s'alimente que par un apport).
        $target = $this->wallet->resolve(
            $userId,
            $validated['account_id'] ?? null,
            $validated['caisse_id'] ?? null,
            inbound: $validated['type'] === 'borrowed',
        );

        $validated['user_id'] = $userId;
        $validated['remaining_amount'] = $validated['amount'];
        $validated['status'] = 'pending';
        $validated['currency_code'] = $validated['currency_code'] ?? $target?->currencyCode;

        $debt = DB::transaction(function () use ($validated, $target) {
            $debt = Debt::create($validated);

            $this->applyCreationEffect($debt, $target);
            $this->journalCreation($debt, $target);

            return $debt;
        });

        return response()->json($debt->load(['account:id,name,currency_code', 'caisse:id,name,currency_code']), 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'type' => 'sometimes|in:lent,borrowed',
            'person_name' => 'sometimes|string|max:255',
            'person_contact' => 'nullable|string|max:255',
            'amount' => 'sometimes|numeric|min:0.01',
            'currency_code' => 'nullable|string|max:3',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'account_id' => 'sometimes|nullable|uuid|exists:accounts,id',
            'caisse_id' => 'sometimes|nullable|uuid|exists:caisses,id',
        ]);

        return DB::transaction(function () use ($request, $id, $validated) {
            $userId = $request->user()->id;
            $debt = Debt::where('user_id', $userId)
                ->with(['account', 'caisse', 'payments'])
                ->findOrFail($id);

            $paid = (float) $debt->payments->sum('amount');

            // Cible d'origine, résolue avant toute modification.
            $oldTarget = $this->targetOf($debt->account_id, $debt->caisse_id, $userId, $debt->type === 'borrowed');

            $this->reverseCreationEffect($debt, $oldTarget);

            $debt->fill($validated);

            if (isset($validated['amount'])) {
                $debt->remaining_amount = max(0, (float) $validated['amount'] - $paid);
            }

            $newTarget = $this->targetOf(
                $debt->account_id,
                $debt->caisse_id,
                $userId,
                $debt->type === 'borrowed',
            );

            if ($newTarget) {
                $debt->currency_code = $validated['currency_code'] ?? $debt->currency_code ?? $newTarget->currencyCode;
            }

            if ((float) $debt->remaining_amount <= 0) {
                $debt->status = 'paid';
            } elseif ($debt->status === 'paid') {
                $debt->status = 'partially_paid';
            }

            $debt->save();

            // On ré-applique sur la nouvelle cible ; les règlements ne bougent pas.
            if ($newTarget) {
                $this->applyCreationEffect($debt, $newTarget);
            }

            // Le journal suit la dette : on remplace la ligne de création.
            $this->purgeJournal($debt->id);
            $this->journalCreation($debt, $newTarget);

            return response()->json(
                $debt->fresh()->load(
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
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'note' => 'nullable|string',
            'account_id' => 'required_without:caisse_id|nullable|uuid|exists:accounts,id',
            'caisse_id' => 'required_without:account_id|nullable|uuid|exists:caisses,id',
        ]);

        return DB::transaction(function () use ($request, $id, $validated) {
            $userId = $request->user()->id;
            $debt = Debt::where('user_id', $userId)->with('account')->findOrFail($id);

            if ($validated['amount'] > (float) $debt->remaining_amount) {
                throw ValidationException::withMessages([
                    'amount' => ['Le paiement dépasse le montant restant dû.'],
                ]);
            }

            // `lent` : on reçoit le remboursement (compte obligatoire).
            // `borrowed` : on verse le remboursement (caisse possible).
            $target = $this->wallet->resolve(
                $userId,
                $validated['account_id'] ?? null,
                $validated['caisse_id'] ?? null,
                inbound: $debt->type === 'lent',
            );

            if (! $target) {
                throw ValidationException::withMessages([
                    'account_id' => ['Choisissez un compte ou une caisse.'],
                ]);
            }

            $amount = $this->wallet->toTargetCurrency(
                (float) $validated['amount'],
                $debt->currency_code ?? $target->currencyCode,
                $target,
            );

            if ($debt->type === 'lent') {
                $this->wallet->credit($target, $amount);
            } else {
                $this->wallet->debit($target, $amount);
            }

            $debt->remaining_amount -= $validated['amount'];
            if ((float) $debt->remaining_amount <= 0) {
                $debt->remaining_amount = 0;
                $debt->status = 'paid';
            } else {
                $debt->status = 'partially_paid';
            }
            $debt->save();

            $payment = DebtPayment::create([
                'debt_id' => $debt->id,
                'account_id' => $target->type === MoneyTarget::ACCOUNT ? $target->id : null,
                'caisse_id' => $target->type === MoneyTarget::CAISSE ? $target->id : null,
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
            ]);

            $this->journalPayment($debt, $payment, $target, $amount);

            return response()->json(
                $debt->fresh()->load(
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
            $debt = Debt::where('user_id', $userId)
                ->with(['account', 'caisse', 'payments.account', 'payments.caisse'])
                ->findOrFail($id);

            $this->reverseCreationEffect($debt, $this->targetOf($debt->account_id, $debt->caisse_id, $userId, $debt->type === 'borrowed'));

            foreach ($debt->payments as $payment) {
                $target = $this->targetOf(
                    $payment->account_id,
                    $payment->caisse_id,
                    $userId,
                    $debt->type === 'lent',
                );

                if (! $target) {
                    continue;
                }

                $amount = $this->wallet->toTargetCurrency(
                    (float) $payment->amount,
                    $debt->currency_code ?? $target->currencyCode,
                    $target,
                );

                // On inverse : un remboursement versé revient dans la cible.
                // Pour un prêt reçu (lent), reprendre l'argent crédité ne doit
                // pas échouer si le solde a été dépensé depuis.
                if ($debt->type === 'lent') {
                    $this->wallet->forceDebit($target, $amount);
                } else {
                    $this->wallet->refund($target, $amount);
                }
            }

            $this->purgeJournal($debt->id);
            $debt->payments()->delete();
            $debt->delete();

            return response()->json(null, 204);
        });
    }

    /**
     * Résout la cible d'une dette ou d'un règlement déjà enregistré.
     */
    protected function targetOf(?string $accountId, ?string $caisseId, string $userId, bool $inbound): ?MoneyTarget
    {
        try {
            return $this->wallet->resolve($userId, $accountId, $caisseId, $inbound);
        } catch (ValidationException|BusinessException) {
            // Donnée historique incohérente (compte supprimé, caisse liée par
            // erreur à un emprunt) : on l'ignore plutôt que de bloquer la
            // suppression ou la modification.
            return null;
        }
    }

    protected function journalCreation(Debt $debt, ?MoneyTarget $target): void
    {
        $lent = $debt->type === 'lent';

        // Cible résolue (donnée historique cohérente) : on écrit dans sa devise.
        if (! $target) {
            return;
        }

        $amount = $this->wallet->toTargetCurrency(
            (float) $debt->amount,
            $debt->currency_code ?? $target->currencyCode,
            $target,
        );

        $this->wallet->journal(
            target: $target,
            direction: $lent ? 'out' : 'in',
            amount: $amount,
            label: ($lent ? 'Prêt accordé : ' : 'Emprunt : ') . $debt->person_name,
            date: $debt->created_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
            relatedType: 'debt',
            relatedId: $debt->id,
            movementType: $lent ? Movement::TYPE_DEBT_LEND : Movement::TYPE_DEBT_BORROW,
        );
    }

    protected function journalPayment(Debt $debt, DebtPayment $payment, MoneyTarget $target, float $amount): void
    {
        $this->wallet->journal(
            target: $target,
            direction: $debt->type === 'lent' ? 'in' : 'out',
            amount: $amount,
            label: ($debt->type === 'lent' ? 'Remboursement reçu : ' : 'Remboursement versé : ') . $debt->person_name,
            date: $payment->date?->format('Y-m-d'),
            relatedType: 'debt_payment',
            relatedId: $payment->id,
            movementType: Movement::TYPE_DEBT_REPAYMENT,
        );
    }

    /**
     * Remove every journal line attached to a debt, so deleting a debt never
     * leaves orphan movements behind.
     */
    protected function purgeJournal(string $debtId): void
    {
        $paymentIds = DebtPayment::where('debt_id', $debtId)->pluck('id');

        Movement::where('related_type', 'debt')->where('related_id', $debtId)->delete();

        if ($paymentIds->isNotEmpty()) {
            Movement::where('related_type', 'debt_payment')->whereIn('related_id', $paymentIds)->delete();
        }
    }

    protected function applyCreationEffect(Debt $debt, ?MoneyTarget $target): void
    {
        if (! $target) {
            return;
        }

        $amount = $this->wallet->toTargetCurrency(
            (float) $debt->amount,
            $debt->currency_code ?? $target->currencyCode,
            $target,
        );

        if ($debt->type === 'lent') {
            // On prête l'argent : il sort du compte ou de la caisse.
            $this->wallet->debit($target, $amount);
        } else {
            // On emprunte : l'argent arrive sur le compte.
            $this->wallet->credit($target, $amount);
        }
    }

    protected function reverseCreationEffect(Debt $debt, ?MoneyTarget $target): void
    {
        if (! $target) {
            return;
        }

        $amount = $this->wallet->toTargetCurrency(
            (float) $debt->amount,
            $debt->currency_code ?? $target->currencyCode,
            $target,
        );

        if ($debt->type === 'lent') {
            $this->wallet->refund($target, $amount);
        } else {
            // On rend l'emprunt : il sort à nouveau. Pas de contrôle de solde,
            // l'argent a bien quitté le compte à l'origine.
            $this->wallet->forceDebit($target, $amount);
        }
    }
}