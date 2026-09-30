<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Caisse;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Saisie rapide : `POST /quick-entries`.
 *
 * Le contrat tient sur deux points : rien n'est écrit si une seule ligne est
 * invalide, et une ligne ne peut pas viser une ressource d'un autre utilisateur.
 */
class QuickEntryTest extends TestCase
{
    use RefreshDatabase;

    private function accountFor(User $user): Account
    {
        return Account::create([
            'user_id' => $user->id,
            'name' => 'Compte '.Str::random(5),
            'type' => 'bank',
            'balance' => 5000.00,
            'currency_code' => 'EUR',
        ]);
    }

    private function categoryFor(User $user, string $type): Category
    {
        return Category::create([
            'user_id' => $user->id,
            'name' => 'Cat '.Str::random(5),
            'type' => $type,
        ]);
    }

    public function test_creates_a_mixed_batch_and_updates_the_account_balance(): void
    {
        $user = User::factory()->create();
        $account = $this->accountFor($user);
        $expenseCat = $this->categoryFor($user, 'expense');
        $incomeCat = $this->categoryFor($user, 'income');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/quick-entries', [
            'entries' => [
                [
                    'type' => 'expense', 'title' => 'Courses', 'amount' => 30,
                    'date' => '2026-03-01', 'account_id' => $account->id,
                    'category_id' => $expenseCat->id, 'currency_code' => 'EUR',
                    'payment_method' => 'cash', 'status' => 'completed',
                ],
                [
                    'type' => 'expense', 'title' => 'Essence', 'amount' => 20,
                    'date' => '2026-03-02', 'account_id' => $account->id,
                    'category_id' => $expenseCat->id, 'currency_code' => 'EUR',
                ],
                [
                    'type' => 'income', 'title' => 'Salaire', 'amount' => 1000,
                    'date' => '2026-03-05', 'account_id' => $account->id,
                    'category_id' => $incomeCat->id, 'currency_code' => 'EUR',
                ],
            ],
        ]);

        $response->assertCreated();
        $this->assertSame(['expenses' => 2, 'incomes' => 1, 'total' => 3], $response->json('created'));

        $this->assertSame(2, Expense::where('user_id', $user->id)->count());
        $this->assertSame(1, Income::where('user_id', $user->id)->count());

        // 5000 - 30 - 20 + 1000
        $this->assertEqualsWithDelta(5950.0, $account->fresh()->balance, 0.001);
    }

    public function test_nothing_is_written_when_a_single_line_is_invalid(): void
    {
        $user = User::factory()->create();
        $account = $this->accountFor($user);
        $expenseCat = $this->categoryFor($user, 'expense');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/quick-entries', [
            'entries' => [
                [
                    'type' => 'expense', 'title' => 'Courses', 'amount' => 30,
                    'date' => '2026-03-01', 'account_id' => $account->id,
                    'category_id' => $expenseCat->id, 'currency_code' => 'EUR',
                ],
                [
                    // Montant absent : la deuxième ligne est invalide.
                    'type' => 'expense', 'title' => 'Essence',
                    'date' => '2026-03-02', 'account_id' => $account->id,
                    'category_id' => $expenseCat->id, 'currency_code' => 'EUR',
                ],
            ],
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);

        // L'erreur est indexée sur la ligne fautive, pas sur le lot entier.
        $this->assertArrayHasKey('entries.1.amount', $response->json('errors'));

        // Rien n'a été écrit, pas même la première ligne valide.
        $this->assertSame(0, Expense::where('user_id', $user->id)->count());
        $this->assertEqualsWithDelta(5000.0, $account->fresh()->balance, 0.001);
    }

    public function test_cannot_target_another_users_account_or_category(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();

        $foreignAccount = $this->accountFor($victim);
        $foreignCategory = $this->categoryFor($victim, 'expense');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/quick-entries', [
            'entries' => [[
                'type' => 'expense', 'title' => 'Vol', 'amount' => 10,
                'date' => '2026-03-01', 'account_id' => $foreignAccount->id,
                'category_id' => $foreignCategory->id, 'currency_code' => 'EUR',
            ]],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Expense::where('user_id', $user->id)->count());
        $this->assertEqualsWithDelta(5000.0, $foreignAccount->fresh()->balance, 0.001);
    }

    /**
     * La saisie rapide envoie `account_id: null` lorsqu'une caisse est choisie.
     * C'est le chemin emprunté quand l'utilisateur renseigne une caisse plutôt
     * qu'un compte sur la ligne.
     */
    public function test_expense_can_be_charged_to_a_caisse_instead_of_an_account(): void
    {
        $user = User::factory()->create();
        $account = $this->accountFor($user);
        $expenseCat = $this->categoryFor($user, 'expense');

        $caisse = Caisse::create([
            'user_id' => $user->id,
            'name' => 'Caisse terrain',
            'budget_amount' => 800.00,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/quick-entries', [
            'entries' => [[
                'type' => 'expense', 'title' => 'Carburant', 'amount' => 120,
                'date' => '2026-03-03', 'account_id' => null, 'caisse_id' => $caisse->id,
                'category_id' => $expenseCat->id, 'currency_code' => 'EUR',
            ]],
        ]);

        $response->assertCreated();

        $expense = Expense::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($caisse->id, $expense->caisse_id);
        $this->assertNull($expense->account_id);

        // La caisse est débitée, le compte laissé intact.
        $this->assertEqualsWithDelta(120.0, $caisse->fresh()->spent_amount, 0.001);
        $this->assertEqualsWithDelta(5000.0, $account->fresh()->balance, 0.001);
    }

    /**
     * `IncomeController` validait `account_id` avec un simple `uuid` : un
     * revenu pouvait donc créditer le solde du compte d'un autre utilisateur.
     * La règle a été complétée par `owned('accounts')` lors de l'extraction des
     * règles partagées.
     */
    public function test_single_income_creation_cannot_credit_a_foreign_account(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();

        $foreignAccount = $this->accountFor($victim);
        $incomeCat = $this->categoryFor($user, 'income');

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/incomes', [
            'title' => 'Salaire',
            'amount' => 5000,
            'date' => '2026-03-05',
            'account_id' => $foreignAccount->id,
            'category_id' => $incomeCat->id,
            'currency_code' => 'EUR',
        ])->assertStatus(422)->assertJsonValidationErrors(['account_id']);

        $this->assertSame(0, Income::where('user_id', $user->id)->count());
        $this->assertEqualsWithDelta(5000.0, $foreignAccount->fresh()->balance, 0.001);
    }

    public function test_rejects_an_unknown_entry_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/quick-entries', [
            'entries' => [['type' => 'transfer', 'title' => 'X', 'amount' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['entries.0.type']);
    }

    public function test_rejects_an_empty_batch(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/quick-entries', [
            'entries' => [],
        ])->assertStatus(422)->assertJsonValidationErrors(['entries']);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/quick-entries', [
            'entries' => [['type' => 'expense', 'title' => 'X', 'amount' => 1]],
        ])->assertUnauthorized();
    }
}
