<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Caisse;
use App\Models\Movement;
use App\Models\Subscription;
use App\Services\Wallet\MoneyTarget;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function __construct(private readonly WalletService $wallet) {}

    public function index(Request $request)
    {
        $subs = Subscription::where('user_id', $request->user()->id)
            ->with('account:id,name,currency_code')
            ->with('caisse:id,name,currency_code')
            ->get();
        return response()->json($subs);
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'currency_code' => 'required|string|size:3',
            'billing_cycle' => 'required|string', // monthly, yearly, etc
            'next_billing_date' => 'required|date',
            'account_id' => ['nullable', 'uuid', Rule::exists('accounts', 'id')->where('user_id', $userId)],
            'caisse_id' => ['nullable', 'uuid', Rule::exists('caisses', 'id')->where('user_id', $userId)],
            'is_active' => 'boolean',
        ]);

        $this->assertSingleTarget($validated);
        $validated['user_id'] = $userId;

        $sub = Subscription::create($validated);
        return response()->json($sub->load(['account:id,name,currency_code', 'caisse:id,name,currency_code']), 201);
    }
    public function update(Request $request, string $id)
    {
        $user_id = $request->user()->id;
        $sub = Subscription::where('user_id',$user_id)->find($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'currency_code' => 'required|string|size:3',
            'billing_cycle' => 'required|string', // monthly, yearly, etc
            'next_billing_date' => 'required|date',
            'account_id' => ['nullable', 'uuid', Rule::exists('accounts', 'id')->where('user_id', $user_id)],
            'caisse_id' => ['nullable', 'uuid', Rule::exists('caisses', 'id')->where('user_id', $user_id)],
            'is_active' => 'boolean',
        ]);

        $this->assertSingleTarget($validated);
        $validated['user_id'] = $user_id;

        $sub->update($validated);
        return response()->json($sub->load(['account:id,name,currency_code', 'caisse:id,name,currency_code']), 201);
    }

    public function toggle(Request $request, string $id)
    {
        $user_id = $request->user()->id;
        $sub = Subscription::where('user_id',$user_id)->find($id);
        $sub->update(['is_active' => !$sub->is_active]);

        return response()->json($sub->load(['account:id,name,currency_code', 'caisse:id,name,currency_code']), 201);
    }

    public function pay(Request $request, $id)
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
            // Permet de payer depuis une autre source que celle de l'abonnement.
            // Optionnel : à défaut, on utilise la cible déjà liée à l'abonnement.
            'account_id' => ['nullable', 'uuid', Rule::exists('accounts', 'id')->where('user_id', $request->user()->id)],
            'caisse_id' => ['nullable', 'uuid', Rule::exists('caisses', 'id')->where('user_id', $request->user()->id)],
        ]);

        return DB::transaction(function () use ($request, $id, $validated) {
            $userId = $request->user()->id;
            $sub = Subscription::where('user_id', $userId)->with(['account', 'caisse'])->findOrFail($id);

            // Cible du paiement : celle de l'abonnement, sauf surcharge explicite.
            $accountId = $validated['account_id'] ?? $sub->account_id;
            $caisseId = $validated['caisse_id'] ?? $sub->caisse_id;

            if (! $accountId && ! $caisseId) {
                throw ValidationException::withMessages([
                    'account_id' => ["Aucun compte ni caisse n'est lié à cet abonnement."],
                ]);
            }

            // Régler un abonnement est toujours une sortie : une caisse convient.
            $target = $this->wallet->resolve($userId, $accountId, $caisseId, inbound: false);

            $amount = $this->wallet->toTargetCurrency(
                (float) $sub->amount,
                $sub->currency_code ?? $target->currencyCode,
                $target,
            );

            $this->wallet->debit($target, $amount);

            // Un abonnement payé est une sortie de trésorerie réelle : sans ligne
            // de journal, elle n'apparaît ni au rapport ni aux analyses.
            $this->wallet->journal(
                target: $target,
                direction: 'out',
                amount: $amount,
                label: "Abonnement : {$sub->name}",
                date: $validated['date'] ?? now()->format('Y-m-d'),
                relatedType: 'subscription',
                relatedId: $sub->id,
            );

            // Advance the billing date based on cycle
            if ($sub->billing_cycle->monthlyMultiplier() > 0) {
                $sub->next_billing_date = $sub->next_billing_date->copy()->addMonths($sub->billing_cycle->monthlyMultiplier());
            } else {
                $sub->next_billing_date = $sub->next_billing_date->copy()->addWeek();
            }
            $sub->save();

            return response()->json(
                $sub->fresh()->load(['account:id,name,currency_code', 'caisse:id,name,currency_code'])
            );
        });
    }

    /**
     * Un compte et une caisse ne sont pas cumulables : la cible doit être unique.
     */
    protected function assertSingleTarget(array $validated): void
    {
        if (! empty($validated['account_id']) && ! empty($validated['caisse_id'])) {
            throw ValidationException::withMessages([
                'account_id' => ['Choisissez soit un compte, soit une caisse, pas les deux.'],
            ]);
        }
    }

    /**
     * Rend à sa source l'argent sorti par un paiement d'abonnement.
     *
     * Le mouvement stocke le montant dans la devise de la cible : on peut donc
     * l'appliquer directement. Une caisse récupère du quota (`spent_amount`),
     * un compte récupère du solde.
     */
    protected function reverseMovement(string $userId, Movement $movement): void
    {
        if ($movement->from_type === 'account' && $movement->from_id) {
            Account::where('user_id', $userId)
                ->where('id', $movement->from_id)
                ->increment('balance', (float) $movement->amount);

            return;
        }

        if ($movement->from_type === 'caisse' && $movement->from_id) {
            $caisse = Caisse::where('user_id', $userId)->find($movement->from_id);

            if ($caisse) {
                // On ne rend jamais plus que ce qui a été consommé.
                $caisse->decrement('spent_amount', min((float) $movement->amount, (float) $caisse->spent_amount));
            }
        }
    }

    public function destroy(Request $request, string $id)
    {
        $user_id = $request->user()->id;
        $sub = Subscription::where('user_id',$user_id)->find($id);

        if (! $sub) {
            throw ValidationException::withMessages([
                'id' => ["Abonnement introuvable."],
            ]);
        }

        // Un abonnement supprimé doit rendre l'argent qu'il a consommé : sans
        // cela, le solde du compte (ou le quota de la caisse) resterait amputé
        // alors que plus aucun paiement n'existe dans les rapports.
        return DB::transaction(function () use ($user_id, $sub) {
            $movements = Movement::where('user_id', $user_id)
                ->where('related_type', 'subscription')
                ->where('related_id', $sub->id)
                ->get();

            foreach ($movements as $movement) {
                $this->reverseMovement($user_id, $movement);
                $movement->delete();
            }

            $sub->delete();

            return response()->json(['message' => 'Abonnement supprimé.']);
        });
    }
}