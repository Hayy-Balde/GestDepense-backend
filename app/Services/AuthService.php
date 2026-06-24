<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function registerUser(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'currency_code' => $data['currency_code'] ?? 'EUR',
            'timezone' => $data['timezone'] ?? 'UTC',
            'locale' => $data['locale'] ?? 'fr',
            'preferences' => [
                'theme' => 'light',
                'compact_mode' => false,
                'notifications_enabled' => true,
                'weekly_report' => false,
                'monthly_report' => true,
            ],
        ]);
    }

    public function authenticate(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        return $user;
    }
}
