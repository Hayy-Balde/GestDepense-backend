<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Régression A-28 : la case « se souvenir de moi » était sans effet. */
class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_remember_extends_the_token_lifetime(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);

        $short = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->json('expires_at');

        $long = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
            'remember' => true,
        ])->json('expires_at');

        $this->assertNotNull($short);
        $this->assertNotNull($long);
        $this->assertGreaterThan(
            strtotime($short),
            strtotime($long),
            '« Se souvenir de moi » ne prolonge pas la session.'
        );
    }
}
