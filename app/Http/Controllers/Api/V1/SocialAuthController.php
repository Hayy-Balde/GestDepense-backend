<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Helpers\UserAgentParser;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OAuthHandoffService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Fournisseurs explicitement autorisés.
     *
     * `{provider}` est un paramètre de route : sans cette liste, la valeur
     * arrivait telle quelle à Socialite, qui choisit la classe de pilote en
     * fonction de la clé de configuration.
     */
    private const ALLOWED_PROVIDERS = ['google'];

    public function __construct(
        protected OAuthHandoffService $handoff,
    ) {}

    public function redirect(string $provider)
    {
        $this->guardProvider($provider);

        // `stateless()` est conservé car l'API est sans cookie de session :
        // le jeton `state` signé par Socialite reste transmis, ce qui lie le
        // callback à la redirection d'origine. La protection CSRF du cycle
        // OAuth repose ici sur ce state, pas sur une session.
        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function callback(Request $request, string $provider)
    {
        $this->guardProvider($provider);

        $socialUser = Socialite::driver($provider)->stateless()->user();

        $email = $socialUser->getEmail();

        if ($email === null) {
            throw ValidationException::withMessages([
                'email' => ["Le fournisseur n'a pas transmis d'adresse email."],
            ]);
        }

        $user = $this->resolveUser($socialUser, $email);

        // Ne pas purger les sessions ici : auparavant chaque connexion Google
        // déconnectait l'utilisateur de tous ses autres appareils. La révocation
        // reste une action explicite (logout, changement de mot de passe).
        return redirect()->away($this->frontendUrl().'/auth/callback?code='.$this->handoff->issue($user));
    }

    /**
     * Échange le code de passerelle contre un jeton d'accès.
     *
     * Appelée en POST par le frontend : le code n'apparaît donc jamais dans un
     * journal d'accès serveur.
     */
    public function exchange(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:64',
        ]);

        $user = $this->handoff->redeem($validated['code']);

        if ($user === null) {
            return response()->json([
                'message' => 'Code de connexion invalide ou expiré.',
            ], 401);
        }

        $deviceName = UserAgentParser::deviceName($request->userAgent());

        $token = $user->createToken(
            $deviceName,
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 60 * 24 * 30)),
        );

        $user->tokens()->latest()->first()?->update([
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    private function resolveUser($socialUser, string $email): User
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            return User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'Utilisateur',
                'email' => $email,
                'google_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
                'password' => Hash::make(Str::password(32)),
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
        }

        // L'identifiant distant n'est enregistré que s'il est réellement
        // inoccupé : l'écraser rattacherait un compte à un fournisseur
        // arbitraire présentant la même adresse.
        $patch = ['avatar' => $socialUser->getAvatar()];

        if ($user->google_id === null) {
            $patch['google_id'] = $socialUser->getId();
        }

        $user->update($patch);

        return $user;
    }

    private function guardProvider(string $provider): void
    {
        abort_unless(in_array($provider, self::ALLOWED_PROVIDERS, true), 404);
    }

    private function frontendUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/');
    }
}
