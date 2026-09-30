<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\UserAgentParser;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\TwoFactorChallengeService;
use App\Services\TwoFactorService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected TwoFactorChallengeService $twoFactorChallenge,
        protected TwoFactorService $twoFactor,
        protected AuditService $audit,
    ) {}

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'currency_code' => 'nullable|string|size:3',
        ]);

        $user = $this->authService->registerUser($validated);

        return $this->issueToken($request, $user, 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        try {
            $user = $this->authService->authenticate($request->email, $request->password);
        } catch (ValidationException $e) {
            // L'échec est journalisé : une série d'échecs sur une adresse est
            // le signal le plus direct d'une tentative de bourrage.
            $this->audit->log(AuditService::LOGIN_FAILED, null, [
                'email' => $request->input('email'),
            ]);

            throw $e;
        }

        // Second facteur obligatoire si et seulement si l'utilisateur l'a activé.
        // Avant ce correctif, la 2FA pouvait être configurée mais n'était jamais
        // demandée : le compte était protégé par le seul mot de passe.
        if ($this->twoFactor->isEnabled($user)) {
            $this->audit->log(AuditService::TWO_FACTOR_CHALLENGED, $user);

            return response()->json([
                'two_factor_required' => true,
                'challenge' => $this->twoFactorChallenge->issue($user),
                'message' => 'Code de vérification requis.',
            ], 200);
        }

        $this->audit->log(AuditService::LOGIN_SUCCEEDED, $user);

        $remember = $request->boolean('remember');

        return $this->issueToken($request, $user, 200, $remember);
    }

    /**
     * Deuxième étape du login : validation du code TOTP ou d'un code de
     * récupération, puis délivrance du jeton d'accès.
     */
    public function verifyTwoFactorLogin(Request $request)
    {
        $validated = $request->validate([
            'challenge' => 'required|string',
            'code' => 'required|string|min:6|max:32',
        ]);

        $user = $this->twoFactorChallenge->resolve($validated['challenge']);

        if ($user === null || ! $this->twoFactor->isEnabled($user)) {
            throw ValidationException::withMessages([
                'challenge' => ['Session de connexion expirée ou invalide.'],
            ]);
        }

        $result = $this->twoFactor->verify($user, $validated['code']);

        if (! $result['ok']) {
            $this->audit->log(AuditService::TWO_FACTOR_CHALLENGE_FAILED, $user);

            throw ValidationException::withMessages([
                'code' => ['Code de vérification invalide.'],
            ]);
        }

        $this->audit->log(AuditService::LOGIN_SUCCEEDED, $user);

        return $this->issueToken($request, $user, 200, $request->boolean('remember'));
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }

        $this->audit->log(AuditService::LOGOUT, $request->user());

        return response()->json(['message' => 'Déconnecté avec succès']);
    }

    /**
     * Crée un jeton d'accès, horodate la session et purge les sessions plus
     * anciennes que la limite configurée.
     *
     * @param  bool  $remember  Lorsque vrai (case « se souvenir de moi »), le
     *                           jeton reçoit la durée de vie longue prévue pour
     *                           ce cas. La case était purement décorative :
     *                           le frontend la cochait toujours, sans effet.
     */
    private function issueToken(Request $request, User $user, int $status = 200, bool $remember = false)
    {
        $deviceName = UserAgentParser::deviceName($request->userAgent());

        $lifetime = $remember
            ? (int) config('auth.remember_token_lifetime_minutes')
            : (int) config('sanctum.expiration');

        $token = $user->createToken($deviceName, ['*'], now()->addMinutes($lifetime));

        $user->tokens()->latest()->first()?->update([
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->pruneOldSessions($user);

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => now()->addMinutes($lifetime)->toIso8601String(),
            // Le modèle complet est renvoyé (et non une projection) pour rester
            // cohérent avec `/auth/user`. Les attributs sensibles restent masqués
            // par l'attribut `#[Hidden]` du modèle User : ni le hash du mot de
            // passe, ni le secret TOTP, ni les codes de récupération.
            'user' => $user,
        ], $status);
    }

    /**
     * Conserve au plus `max_sessions` jetons par utilisateur. Sans cela, un
     * attaquant qui obtient des identifiants peut ouvrir un nombre illimité de
     * sessions concurrentes, toutes valables jusqu'à expiration.
     */
    private function pruneOldSessions(User $user): void
    {
        $max = (int) config('auth.max_sessions', (int) env('AUTH_MAX_SESSIONS', 10));

        if ($max < 1) {
            return;
        }

        $keep = $user->tokens()
            ->latest('created_at')
            ->take($max)
            ->pluck('id');

        $user->tokens()->whereNotIn('id', $keep)->delete();
    }

    public function verifyPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if (! Hash::check($request->password, $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => ['Mot de passe incorrect.'],
            ]);
        }

        return response()->json(['message' => 'Mot de passe vérifié.']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $user->update($request->only(['name', 'currency_code', 'timezone', 'locale', 'preferences']));
        return response()->json($user);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        Password::sendResetLink($request->only('email'));

        // Réponse identique que l'adresse existe ou non : la distinguer
        // permettrait d'énumérer les comptes enregistrés.
        return response()->json([
            'message' => 'Si un compte correspond à cette adresse, un lien de réinitialisation vous a été envoyé par email.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                // Le mot de passe ayant changé, toutes les sessions existantes
                // deviennent caduques : sinon un jeton volé reste valide.
                $user->tokens()->delete();

                $this->audit->log(AuditService::PASSWORD_RESET, $user);

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Mot de passe réinitialisé avec succès.']);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}
