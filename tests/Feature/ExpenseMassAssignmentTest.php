<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Régression A-02 : `PUT /expenses/{id}` recevait `$request->all()`.
 *
 * Le modèle `Expense` n'a qu'un `$guarded = ['id']`, ce qui rendait assignable
 * n'importe quelle colonne de la table. Un utilisateur pouvait donc renvoyer
 * `user_id` et transférer une dépense — ainsi son impact budgétaire — vers le
 * compte d'un autre utilisateur.
 */
class ExpenseMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpense(User $owner, float $amount = 10.00): Expense
    {
        $account = Account::create([
            'user_id' => $owner->id,
            'name' => 'Compte courant',
            'type' => 'bank',
            'balance' => 1000.00,
            'currency_code' => 'EUR',
        ]);

        $category = Category::create([
            'user_id' => $owner->id,
            'name' => 'Alimentation '.Str::random(5),
            'type' => 'expense',
        ]);

        return Expense::create([
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => $amount,
            'title' => 'Courses',
            'date' => now()->toDateString(),
            'currency_code' => 'EUR',
        ]);
    }

    public function test_update_cannot_transfer_an_expense_to_another_user(): void
    {
        $victim = User::factory()->create();
        $attacker = User::factory()->create();

        $expense = $this->makeExpense($victim);

        $response = $this->actingAs($attacker, 'sanctum')
            ->putJson("/api/v1/expenses/{$expense->id}", [
                'user_id' => $attacker->id,
                'amount' => 10.00,
            ]);

        // Soit la dépense est hors de portée (404), soit elle reste à son
        // propriétaire. Dans les deux cas elle n'a pas pu être déplacée.
        if ($response->status() === 200) {
            $this->assertSame(
                $victim->id,
                $expense->fresh()->user_id,
                'La dépense a été transférée vers un autre utilisateur.'
            );
        } else {
            $this->assertContains($response->status(), [403, 404, 422]);
        }
    }

    public function test_update_rejects_columns_outside_the_whitelist(): void
    {
        $user = User::factory()->create();
        $expense = $this->makeExpense($user);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/expenses/{$expense->id}", [
                'amount' => 42.00,
                'is_admin' => true,
            ]);

        $fresh = $expense->fresh();

        $this->assertSame(42.0, (float) $fresh->amount);
        $this->assertSame($user->id, $fresh->user_id);
    }

    public function test_update_ignores_a_user_id_that_references_another_account(): void
    {
        $user = User::factory()->create();
        $expense = $this->makeExpense($user);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/expenses/{$expense->id}", [
                'amount' => 15.00,
                'user_id' => Str::uuid()->toString(),
            ]);

        $this->assertSame($user->id, $expense->fresh()->user_id);
    }
}
