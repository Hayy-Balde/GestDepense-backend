<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Régression A-03 : la 2FA pouvait être activée mais n'était jamais vérifiée.
 *
 * `login` renvoyait directement un jeton d'accès, quel que soit l'état de la
 * 2FA. Un mot de passe compromis suffisait donc à prendre le compte, même chez
 * un utilisateur qui avait pourtant activé un second facteur.
 */
class TwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTwoFactor(): User
    {
        return User::factory()->create([
            'password' => Hash::make('Password123!'),
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => json_encode(['ABCD1234', 'EFGH5678']),
        ]);
    }

    private function login(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/auth/login', $payload);
    }

    public function test_login_with_two_factor_enabled_does_not_return_a_token(): void
    {
        $user = $this->userWithTwoFactor();

        $response = $this->login([
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertOk();
        $response->assertJson(['two_factor_required' => true]);
        $response->assertJsonStructure(['challenge']);
        $response->assertJsonMissingPath('access_token');

        $this->assertSame(0, $user->tokens()->count(), 'Un jeton a été délivré sans 2FA.');
    }

    public function test_login_returns_a_token_when_two_factor_is_not_enabled(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);

        $response = $this->login([
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['access_token', 'token_type', 'user']);
    }

    public function test_a_wrong_second_factor_is_rejected_and_issues_no_token(): void
    {
        $user = $this->userWithTwoFactor();

        $challenge = $this->login([
            'email' => $user->email,
            'password' => 'Password123!',
        ])->json('challenge');

        $response = $this->postJson('/api/v1/auth/login/2fa', [
            'challenge' => $challenge,
            'code' => '000000',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_tampered_challenge_is_rejected(): void
    {
        $user = $this->userWithTwoFactor();

        $response = $this->postJson('/api/v1/auth/login/2fa', [
            'challenge' => str_repeat('A', 64),
            'code' => '123456',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_challenge_cannot_be_replayed(): void
    {
        $user = $this->userWithTwoFactor();

        $challenge = $this->login([
            'email' => $user->email,
            'password' => 'Password123!',
        ])->json('challenge');

        // Un code de récupération est à usage unique : le même challenge ne
        // doit pas pouvoir être rejoué avec le code déjà consommé.
        $first = $this->postJson('/api/v1/auth/login/2fa', [
            'challenge' => $challenge,
            'code' => 'ABCD1234',
        ]);

        $first->assertOk();

        $user->tokens()->delete();

        $replay = $this->postJson('/api/v1/auth/login/2fa', [
            'challenge' => $challenge,
            'code' => 'EFGH5678',
        ]);

        $replay->assertStatus(422);
    }

    public function test_a_recovery_code_is_consumed_and_works(): void
    {
        $user = $this->userWithTwoFactor();

        $challenge = $this->login([
            'email' => $user->email,
            'password' => 'Password123!',
        ])->json('challenge');

        $response = $this->postJson('/api/v1/auth/login/2fa', [
            'challenge' => $challenge,
            'code' => 'abcd1234',
        ]);

        $response->assertOk();
        $this->assertSame(1, $user->tokens()->count());
    }
}
