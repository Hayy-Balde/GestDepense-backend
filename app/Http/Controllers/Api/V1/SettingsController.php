<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OTPHP\TOTP;

class SettingsController extends Controller
{
    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
        ]);

        $request->user()->update($validated);

        return response()->json([
            'message' => 'Profil mis à jour avec succès.',
            'user' => $request->user()->fresh(),
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $request->validate([
            'currency_code' => 'sometimes|string|size:3',
            'timezone' => 'sometimes|string|max:50',
            'locale' => 'sometimes|string|size:2',
            'preferences' => 'sometimes|array',
            'preferences.theme' => 'sometimes|in:light,dark,system',
            'preferences.compact_mode' => 'sometimes|boolean',
            'preferences.notifications_enabled' => 'sometimes|boolean',
            'preferences.weekly_report' => 'sometimes|boolean',
            'preferences.monthly_report' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $data = $request->only(['currency_code', 'timezone', 'locale']);

        if ($request->has('preferences')) {
            $existingPrefs = $user->preferences ?? [];
            $data['preferences'] = array_merge($existingPrefs, $request->preferences);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Préférences enregistrées.',
            'user' => $user->fresh(),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return response()->json([
            'message' => 'Mot de passe mis à jour avec succès.',
        ]);
    }

    public function deleteAccount(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Compte supprimé avec succès.',
        ]);
    }

    public function sessions(Request $request)
    {
        $currentTokenId = $request->user()->currentAccessToken()->id ?? null;

        $tokens = $request->user()->tokens()
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'ip' => $token->ip ?? 'N/A',
                'user_agent' => $token->user_agent ?? 'N/A',
                'last_used_at' => $token->last_used_at?->diffForHumans() ?? 'Jamais',
                'created_at' => $token->created_at->diffForHumans(),
                'is_current' => $token->id === $currentTokenId,
            ]);

        return response()->json($tokens);
    }

    public function revokeSession(Request $request, string $id)
    {
        $token = $request->user()->tokens()->where('id', $id)->first();

        if (! $token) {
            return response()->json(['message' => 'Session introuvable.'], 404);
        }

        $token->delete();

        return response()->json(['message' => 'Session révoquée.']);
    }

    public function enable2fa(Request $request)
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return response()->json(['message' => '2FA déjà activée.'], 400);
        }

        $otp = TOTP::create();
        $otp->setLabel($user->email);
        $otp->setIssuer(config('app.name'));
        $secret = $otp->getSecret();

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json([
            'secret' => $secret,
            'qr_code_uri' => $otp->getProvisioningUri(),
            'recovery_codes' => null,
        ]);
    }

    public function verify2fa(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json(['message' => '2FA non initialisée.'], 400);
        }

        $otp = TOTP::create($user->two_factor_secret);
        $window = null;

        if (! $otp->verify($request->code, null, 1)) {
            throw ValidationException::withMessages([
                'code' => ['Code invalide.'],
            ]);
        }

        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        }

        $user->update([
            'two_factor_recovery_codes' => json_encode($recoveryCodes),
            'two_factor_confirmed_at' => now(),
        ]);

        return response()->json([
            'message' => '2FA activée avec succès.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function disable2fa(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json(['message' => '2FA non activée.'], 400);
        }

        $otp = TOTP::create($user->two_factor_secret);

        if (! $otp->verify($request->code, null, 1)) {
            throw ValidationException::withMessages([
                'code' => ['Code invalide.'],
            ]);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json([
            'message' => '2FA désactivée.',
        ]);
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $user = $request->user();

        if ($user->avatar && ! str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => Storage::disk('public')->url($path)]);

        return response()->json([
            'message' => 'Avatar mis à jour.',
            'user' => $user->fresh(),
        ]);
    }

    public function twoFactorStatus(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'enabled' => ! is_null($user->two_factor_confirmed_at),
            'has_secret' => ! is_null($user->two_factor_secret),
            'confirmed_at' => $user->two_factor_confirmed_at,
        ]);
    }
}
