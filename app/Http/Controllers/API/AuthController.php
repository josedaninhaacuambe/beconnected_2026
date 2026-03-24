<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'nullable|in:customer,store_owner',
            'province_id' => 'nullable|exists:provinces,id',
            'city_id' => 'nullable|exists:cities,id',
        ]);

        $user = User::create([
            ...$validated,
            'role' => $validated['role'] ?? 'customer',
        ]);

        $token = $user->createToken('beconnect-app')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            return response()->json(['message' => 'Conta suspensa. Contacte o suporte.'], 403);
        }

        $token = $user->createToken('beconnect-app')->plainTextToken;

        return response()->json([
            'user' => $user->load(['province', 'city']),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sessão terminada com sucesso.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        // Cache user profile for 60s — /auth/me is polled on every app load
        $data = Cache::remember("user_me_{$user->id}", 60, fn() =>
            $user->load(['province', 'city', 'neighborhood'])->toArray()
        );

        return response()->json($data);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $user->id,
            'province_id' => 'nullable|exists:provinces,id',
            'city_id' => 'nullable|exists:cities,id',
            'neighborhood_id' => 'nullable|exists:neighborhoods,id',
            'address' => 'nullable|string|max:500',
            'avatar' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $path;
        }

        $user->update($validated);
        Cache::forget("user_me_{$user->id}");

        return response()->json($user->fresh()->load(['province', 'city']));
    }

    /**
     * Aplicar role após registo com Google (ex: store_owner)
     * Só permite customer → store_owner (admin define roles superiores manualmente)
     */
    public function claimRole(Request $request): JsonResponse
    {
        $request->validate(['role' => 'required|in:customer,store_owner']);

        $user = $request->user();

        // Apenas permite elevar de customer para store_owner
        if ($user->role === 'customer' && $request->role === 'store_owner') {
            $user->update(['role' => 'store_owner']);
        }

        return response()->json(['user' => $user->fresh()->load(['province', 'city'])]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $request->user()->password)) {
            return response()->json(['message' => 'Senha atual incorreta.'], 422);
        }

        $request->user()->update(['password' => $request->password]);

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    }
}
