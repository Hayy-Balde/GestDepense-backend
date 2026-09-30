<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Validation\Rule;

/**
 * Règles de validation d'une écriture simple (dépense ou revenu).
 *
 * Ces règles vivaient dans les contrôleurs `ExpenseController` et
 * `IncomeController`. La saisie rapide en masse doit valider exactement les
 * mêmes choses : les dupliquer ici reviendrait à les faire diverger au premier
 * correctif, et l'endpoint de masse accepterait alors des écritures que la
 * création unitaire refuse.
 */
trait DefinesEntryRules
{
    use ValidatesTargets;

    /**
     * Noms lisibles des attributs, pour que les messages d'erreur ne parlent
     * pas de `entries.0.account_id`.
     */
    protected function entryAttributeNames(): array
    {
        return [
            'amount' => 'montant',
            'title' => 'titre',
            'date' => 'date',
            'currency_code' => 'devise',
            'category_id' => 'catégorie',
            'account_id' => 'compte',
            'caisse_id' => 'caisse',
            'payment_method' => 'moyen de paiement',
            'status' => 'statut',
            'description' => 'description',
            'notes' => 'notes',
            'is_recurring' => 'récurrent',
            'recurrence_rule' => 'règle de récurrence',
        ];
    }

    /**
     * Liste blanche des champs acceptés par une écriture.
     *
     * `update()` ne doit jamais recevoir `$request->all()` : le modèle n'a qu'un
     * `$guarded = ['id']`, donc n'importe quelle colonne de la table est
     * assignable (y compris `user_id`, vers un autre utilisateur).
     *
     * @return array<string, mixed>
     */
    protected function expenseRules(bool $isUpdate): array
    {
        $required = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'amount' => $required.'|numeric|min:0.01',
            'title' => $required.'|string|max:255',
            'date' => $required.'|date',
            'currency_code' => $required.'|string|size:3',
            'category_id' => [$required, 'uuid', $this->ownedOrSystemCategory()],
            // Une dépense sort soit d'un compte, soit d'une caisse.
            'account_id' => [
                $isUpdate ? 'sometimes' : 'required_without:caisse_id',
                'nullable', 'uuid', $this->owned('accounts'),
            ],
            'caisse_id' => ['nullable', 'uuid', $this->owned('caisses')],
            'description' => 'sometimes|nullable|string|max:1000',
            'notes' => 'sometimes|nullable|string|max:1000',
            'payment_method' => ['sometimes', 'nullable', 'string', Rule::in([
                'cash', 'bank_transfer', 'mobile_money', 'credit_card', 'debit_card', 'check', 'other',
            ])],
            'status' => ['sometimes', 'string', Rule::in(['pending', 'completed', 'cancelled'])],
            'is_recurring' => 'sometimes|boolean',
            'recurrence_rule' => 'sometimes|nullable|string|max:255',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function incomeRules(bool $isUpdate): array
    {
        $required = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'amount' => $required.'|numeric|min:0.01',
            'title' => $required.'|string|max:255',
            'account_id' => $required.'|uuid|'.$this->owned('accounts'),
            'category_id' => [$required, 'uuid', $this->ownedOrSystemCategory()],
            'date' => $required.'|date',
            'currency_code' => $required.'|string|size:3',
            'payment_method' => ['sometimes', 'nullable', 'string', Rule::in([
                'cash', 'bank_transfer', 'mobile_money', 'credit_card', 'debit_card', 'check', 'other',
            ])],
            'is_recurring' => 'sometimes|boolean',
            'recurrence_rule' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'notes' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
