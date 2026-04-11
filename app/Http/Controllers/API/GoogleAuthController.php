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
     * Retorna a URL de redirect para o Google OAuth
     * O frontend usa esta URL para iniciar o fluxo
     */
    public function redirectUrl(): JsonResponse
    {
        $url = Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    /**
     * Callback do Google — recebe o código, troca por token, autentica o user
     */
    public function callback(Request $request): JsonResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
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
                // Mudar role para store_owner e criar loja
                $user->update(['role' => 'store_owner']);

                Store::create([
                    'user_id'     => $user->id,
                    'name'        => $storeData['name'],
                    'description' => $storeData['description'] ?? null,
                    'phone'       => $storeData['phone'],
                    'whatsapp'    => $storeData['whatsapp'] ?? null,
                    'address'     => $storeData['address'] ?? null,
                    'is_active'   => true, // loja ativa pois email já verificado pelo Google
                ]);

                // Limpar dados da sessão
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
     * Callback via redirect (para apps web que usam redirect flow)
     * Redireciona para o frontend com o token
     */
    public function callbackRedirect(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            return redirect(config('app.url') . '/login?error=google_failed');
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (!$user) {
            $user = User::create([
                'google_id' => $googleUser->getId(),
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_avatar' => $googleUser->getAvatar(),
                'avatar' => $googleUser->getAvatar(),
                'google_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken,
                'role' => 'customer',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            // Verificar se há dados de loja na sessão (registro com loja)
            $storeData = session('store_registration_data');
            if ($storeData) {
                // Mudar role para store_owner e criar loja
                $user->update(['role' => 'store_owner']);

                Store::create([
                    'user_id'     => $user->id,
                    'name'        => $storeData['name'],
                    'description' => $storeData['description'] ?? null,
                    'phone'       => $storeData['phone'],
                    'whatsapp'    => $storeData['whatsapp'] ?? null,
                    'address'     => $storeData['address'] ?? null,
                    'is_active'   => true, // loja ativa pois email já verificado pelo Google
                ]);

                // Limpar dados da sessão
                session()->forget('store_registration_data');
            }
        } else {
            $user->update([
                'google_id' => $googleUser->getId(),
                'google_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken,
            ]);
        }

        if (!$user->is_active) {
            return redirect(config('app.url') . '/login?error=account_suspended');
        }

        $token = $user->createToken('beconnect-google')->plainTextToken;

        // Redireciona para frontend com token na URL (SPA apanha e guarda)
        return redirect(config('app.url') . '/auth/google/success?token=' . $token . '&user=' . urlencode(json_encode([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'avatar' => $user->avatar ?? $user->google_avatar,
        ])));
    }

    /**
     * Iniciar registro com Google e dados da loja
     * Armazena dados da loja na sessão e redireciona para Google OAuth
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

        // Armazenar dados da loja na sessão para usar após callback do Google
        session(['store_registration_data' => $validated['store']]);

        $url = Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json(['redirect_url' => $url]);
    }
}
