<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    public function redirect(string $provider)
    {
        return Socialite::driver($provider)
            ->stateless()
            ->redirect();
    }

    public function callback(string $provider)
    {
        $socialUser = Socialite::driver($provider)->stateless()->user();

        $user = User::where('email', $socialUser->getEmail())->first();

        if (! $user) {
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                'email' => $socialUser->getEmail(),
                'google_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
                'password' => bcrypt(Str::password(32)),
                'currency_code' => 'EUR',
                'timezone' => 'UTC',
                'locale' => 'fr',
                'preferences' => [
                    'theme' => 'light',
                    'compact_mode' => false,
                    'notifications_enabled' => true,
                    'weekly_report' => false,
                    'monthly_report' => true,
                ],
            ]);
        } else {
            $user->update([
                'google_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        $frontendUrl = env('FRONTEND_URL', 'https://gestdepense.vercel.app');

        return redirect()->away($frontendUrl . '/auth/callback?token=' . $token . '&user=' . urlencode($user->toJson()));
    }
}
