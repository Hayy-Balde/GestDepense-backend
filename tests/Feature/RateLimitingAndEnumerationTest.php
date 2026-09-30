<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AccountType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Régression A-04 et A-14 : absence de quotas et énumération de comptes.
 */
class RateLimitingAndEnumerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);

        // Le seuil est de 5/min sur la paire (email, IP).
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'MauvaisMotDePasse123!',
            ]);
        }

        $blocked = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'MauvaisMotDePasse123!',
        ]);

        $this->assertSame(429, $blocked->status(), 'Le bourrage de mot de passe n\'est pas limité.');
    }

    public function test_forgot_password_does_not_reveal_whether_an_account_exists(): void
    {
        $existing = User::factory()->create();

        $known = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $existing->email,
        ]);

        $unknown = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'absent-'.__LINE__.'@example.invalid',
        ]);

        $known->assertOk();
        $unknown->assertOk();

        $this->assertSame(
            $known->json(),
            $unknown->json(),
            'Les réponses diffèrent : un attaquant peut énumérer les comptes.'
        );
    }

    public function test_global_config_write_is_forbidden_for_a_regular_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/config/account-types', [
                'value' => 'un-type-injecte',
                'label' => 'Type injecté',
            ])
            ->assertForbidden();
    }

    public function test_system_categories_cannot_be_updated_or_deleted(): void
    {
        $user = User::factory()->create();

        $system = Category::create([
            'user_id' => null,
            'name' => 'Système',
            'type' => 'expense',
            'is_system' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/categories/{$system->id}", ['name' => 'Renommé'])
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/categories/{$system->id}")
            ->assertNotFound();

        $this->assertSame('Système', $system->fresh()->name);
    }

    public function test_admin_can_write_global_config(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/config/account-types', [
                'value' => 'un-type-valide',
                'label' => 'Type valide',
            ])
            ->assertSuccessful();

        $this->assertSame(1, AccountType::where('value', 'un-type-valide')->count());
    }
}
