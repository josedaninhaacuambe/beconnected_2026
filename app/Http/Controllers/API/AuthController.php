<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\StoreEmployee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ─── Gerar OTP de 6 dígitos ───────────────────────────────────────────────
    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function sendOtp(User $user): void
    {
        $otp = $this->generateOtp();
        $user->update([
            'otp'            => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);
        Mail::to($user->email)->send(new OtpMail($otp, $user->name));
    }

    // ─── Registo com email — cria conta e envia OTP ───────────────────────────
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|max:255|unique:users',
            'phone'                 => 'nullable|string|max:20|unique:users',
            'password'              => 'required|string|min:8|confirmed',
            'role'                  => 'nullable|in:customer,store_owner',
            'province_id'           => 'nullable|exists:provinces,id',
            'city_id'               => 'nullable|exists:cities,id',
        ]);

        $user = User::create([
            ...$validated,
            'role'           => $validated['role'] ?? 'customer',
            'email_verified' => false,
            'is_active'      => false, // inactivo até verificar email
        ]);

        $this->sendOtp($user);

        return response()->json([
            'requires_otp' => true,
            'email'        => $user->email,
            'message'      => 'Código enviado para ' . $user->email,
        ], 201);
    }

    // ─── Verificar OTP ────────────────────────────────────────────────────────
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp'   => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->email_verified) {
            return response()->json(['message' => 'Email já verificado.'], 422);
        }

        if (!$user->otp || $user->otp !== $request->otp) {
            return response()->json(['message' => 'Código inválido.'], 422);
        }

        if (!$user->otp_expires_at || now()->isAfter($user->otp_expires_at)) {
            return response()->json(['message' => 'Código expirado. Solicita um novo.'], 422);
        }

        $user->update([
            'email_verified'    => true,
            'email_verified_at' => now(),
            'is_active'         => true,
            'otp'               => null,
            'otp_expires_at'    => null,
        ]);

        $token = $user->createToken('beconnect-app')->plainTextToken;

        return response()->json([
            'user'  => $user->fresh()->load(['province', 'city']),
            'token' => $token,
        ]);
    }

    // ─── Reenviar OTP ─────────────────────────────────────────────────────────
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->email_verified) {
            return response()->json(['message' => 'Email já verificado.'], 422);
        }

        // Throttle: não reenviar se OTP ainda válido há menos de 1 min
        if ($user->otp_expires_at && now()->diffInSeconds($user->otp_expires_at) > 540) {
            return response()->json(['message' => 'Aguarda 1 minuto antes de pedir novo código.'], 429);
        }

        $this->sendOtp($user);

        return response()->json(['message' => 'Novo código enviado para ' . $user->email]);
    }

    // ─── Login ────────────────────────────────────────────────────────────────
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $user = Auth::user();

        // Conta não verificada — reenviar OTP e pedir verificação
        if (!$user->email_verified) {
            $this->sendOtp($user);
            Auth::logout();
            return response()->json([
                'requires_otp' => true,
                'email'        => $user->email,
                'message'      => 'Verifica o teu email. Código reenviado.',
            ], 403);
        }

        if (!$user->is_active) {
            Auth::logout();
            return response()->json(['message' => 'Conta suspensa. Contacte o suporte.'], 403);
        }

        $token = $user->createToken('beconnect-app')->plainTextToken;

        return response()->json([
            'user'  => $user->load(['province', 'city']),
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
        $data = Cache::remember("user_me_{$user->id}", 60, fn() =>
            $user->load(['province', 'city', 'neighborhood', 'store'])->toArray()
        );

        // Incluir permissões POS do funcionário (se não for dono/admin)
        if (!in_array($user->role, ['store_owner', 'admin'])) {
            $emp = StoreEmployee::where('user_id', $user->id)
                ->where('is_active', true)
                ->select(['id', 'store_id', 'role', 'permissions'])
                ->first();
            $data['pos_employee'] = $emp?->toArray();
        } else {
            $data['pos_employee'] = null;
        }

        return response()->json($data);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'             => 'sometimes|string|max:255',
            'phone'            => 'sometimes|string|max:20|unique:users,phone,' . $user->id,
            'province_id'      => 'nullable|exists:provinces,id',
            'city_id'          => 'nullable|exists:cities,id',
            'neighborhood_id'  => 'nullable|exists:neighborhoods,id',
            'address'          => 'nullable|string|max:500',
            'avatar'           => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $path;
        }

        $user->update($validated);
        Cache::forget("user_me_{$user->id}");

        return response()->json($user->fresh()->load(['province', 'city']));
    }

    public function claimRole(Request $request): JsonResponse
    {
        $request->validate(['role' => 'required|in:customer,store_owner']);
        $user = $request->user();
        if ($user->role === 'customer' && $request->role === 'store_owner') {
            $user->update(['role' => 'store_owner']);
        }
        return response()->json(['user' => $user->fresh()->load(['province', 'city'])]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $request->user()->password)) {
            return response()->json(['message' => 'Senha atual incorreta.'], 422);
        }

        $request->user()->update(['password' => $request->password]);
        return response()->json(['message' => 'Senha alterada com sucesso.']);
    }
}
