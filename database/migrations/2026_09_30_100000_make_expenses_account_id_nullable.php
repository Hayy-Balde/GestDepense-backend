<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rend `expenses.account_id` nullable.
 *
 * La colonne a été créée `NOT NULL`, alors que tout le reste du code prévoit
 * qu'une dépense sorte d'une caisse plutôt que d'un compte :
 *
 *   - `ExpenseController` valide `account_id` en `required_without:caisse_id` ;
 *   - `ExpenseService::createExpense()` ne débite le compte que s'il n'y a pas
 *     de caisse, et appelle `spendFromCaisse()` sinon ;
 *   - le formulaire de dépense propose un sélecteur « Caisse (optionnel) ».
 *
 * Concrètement, toute dépense rattachée à une caisse renvoyait une erreur 500
 * sur contrainte `NOT NULL`. Élargir une contrainte est sans risque pour les
 * données existantes : les lignes actuelles ont toutes un compte renseigné.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignUuid('account_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Impossible de revenir en arrière sans traitement : des dépenses
        // peuvent désormais pointer une caisse et n'auraient pas de compte.
        // La migration est donc volontairement irréversible.
    }
};
