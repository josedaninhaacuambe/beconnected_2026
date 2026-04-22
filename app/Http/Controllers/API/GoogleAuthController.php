<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Gera o redirect_uri dinamicamente a partir do host actual da request.
     * Garante que staging e produção usam sempre o seu próprio domínio,
     * sem depender da variável APP_URL do .env.
     */
    private function callbackUrl(Request $request): string
    {
        return $request->getSchemeAndHttpHost() . '/api/auth/google/callback';
    }

    /**
     * Retorna a URL de redirect para o Google OAuth.
     * O frontend usa esta URL para iniciar o fluxo.
     */
    public function redirectUrl(Request $request): JsonResponse
    {
        $url = Socialite::driver('google')
            ->stateless()
            ->redirectUrl($this->callbackUrl($request))
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    /**
     * Callback do Google — recebe o código, troca por token, autentica o user.
     */
    public function callback(Request $request): JsonResponse
    {
        try {
            // redirect_uri deve ser o mesmo usado na autorização (obrigatório pelo OAuth2)
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->redirectUrl($this->callbackUrl($request))
                ->user();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Falha ao autenticar com Google. Tente novamente.'], 422);
        }

        $googleId = $googleUser->getId();
        $cacheKey = "google_user_{$googleId}";

        // Try cache first (5 min TTL) — avoids DB lookup on every login
        $cachedUserId = Cache::get($cacheKey);
        $user = $cachedUserId ? User::find($cachedUserId) : null;

        if (!$user) {
            // Procurar por google_id ou email
            $user = User::where('google_id', $googleId)
                ->orWhere('email', $googleUser->getEmail())
                ->first();
        }

        if ($user) {
            // Utilizador existente — actualizar dados Google
            $user->update([
                'google_id'            => $googleId,
                'google_token'         => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken,
                'google_avatar'        => $googleUser->getAvatar(),
                'avatar'               => $user->avatar ?? $googleUser->getAvatar(),
            ]);
        } else {
            // Novo utilizador — criar conta automaticamente
            $user = User::create([
                'google_id'            => $googleId,
                'name'                 => $googleUser->getName(),
                'email'                => $googleUser->getEmail(),
                'google_avatar'        => $googleUser->getAvatar(),
                'avatar'               => $googleUser->getAvatar(),
                'google_token'         => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken,
                'role'                 => 'customer',
                'is_active'            => true,
                'email_verified_at'    => now(),
            ]);

            // Verificar se há dados de loja na sessão (registro com loja)
            $storeData = session('store_registration_data');
            if ($storeData) {
                $user->update(['role' => 'store_owner']);

                Store::create([
                    'user_id'     => $user->id,
                    'name'        => $storeData['name'],
                    'description' => $storeData['description'] ?? null,
                    'phone'       => $storeData['phone'],
                    'whatsapp'    => $storeData['whatsapp'] ?? null,
                    'address'     => $storeData['address'] ?? null,
                    'is_active'   => true,
                ]);

                session()->forget('store_registration_data');
            }
        }

        // Cache google_id → user_id for 5 minutes
        Cache::put($cacheKey, $user->id, 300);

        if (!$user->is_active) {
            return response()->json(['message' => 'Conta suspensa. Contacte o suporte.'], 403);
        }

        $token = $user->createToken('beconnect-google')->plainTextToken;

        return response()->json([
            'user'         => $user->load(['province', 'city']),
            'token'        => $token,
            'is_new_user'  => $user->wasRecentlyCreated,
        ]);
    }

    /**
     * Callback via redirect (para apps web que usam redirect flow).
     * Redireciona para o frontend com o token.
     */
    public function callbackRedirect(Request $request)
    {
        $baseUrl = $request->getSchemeAndHttpHost();

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->redirectUrl($this->callbackUrl($request))
                ->user();
        } catch (\Exception $e) {
            return redirect($baseUrl . '/login?error=google_failed');
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (!$user) {
            $user = User::create([
                'google_id'            => $googleUser->getId(),
                'name'                 => $googleUser->getName(),
                'email'                => $googleUser->getEmail(),
                'google_avatar'        => $googleUser->getAvatar(),
                'avatar'               => $googleUser->getAvatar(),
                'google_token'         => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken,
                'role'                 => 'customer',
                'is_active'            => true,
                'email_verified_at'    => now(),
            ]);

            $storeData = session('store_registration_data');
            if ($storeData) {
                $user->update(['role' => 'store_owner']);

                Store::create([
                    'user_id'     => $user->id,
                    'name'        => $storeData['name'],
                    'description' => $storeData['description'] ?? null,
                    'phone'       => $storeData['phone'],
                    'whatsapp'    => $storeData['whatsapp'] ?? null,
                    'address'     => $storeData['address'] ?? null,
                    'is_active'   => true,
                ]);

                session()->forget('store_registration_data');
            }
        } else {
            $user->update([
                'google_id'            => $googleUser->getId(),
                'google_token'         => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken,
            ]);
        }

        if (!$user->is_active) {
            return redirect($baseUrl . '/login?error=account_suspended');
        }

        $token = $user->createToken('beconnect-google')->plainTextToken;

        return redirect($baseUrl . '/auth/google/success?token=' . $token . '&user=' . urlencode(json_encode([
            'id'     => $user->id,
            'name'   => $user->name,
            'email'  => $user->email,
            'role'   => $user->role,
            'avatar' => $user->avatar ?? $user->google_avatar,
        ])));
    }

    /**
     * Iniciar registro com Google e dados da loja.
     * Armazena dados da loja na sessão e redireciona para Google OAuth.
     */
    public function registerWithStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store.name'        => 'required|string|max:255',
            'store.description' => 'nullable|string|max:1000',
            'store.phone'       => 'required|string|max:20',
            'store.whatsapp'    => 'nullable|string|max:20',
            'store.address'     => 'nullable|string|max:500',
        ]);

        session(['store_registration_data' => $validated['store']]);

        $url = Socialite::driver('google')
            ->stateless()
            ->redirectUrl($this->callbackUrl($request))
            ->redirect()
            ->getTargetUrl();

        return response()->json(['redirect_url' => $url]);
    }
}
