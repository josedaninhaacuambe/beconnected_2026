<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
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
        $messages = [
            'name.required'                 => 'O nome é obrigatório.',
            'name.string'                   => 'O nome deve ser um texto.',
            'name.max'                      => 'O nome pode ter no máximo 255 caracteres.',
            'email.required'                => 'O email é obrigatório.',
            'email.string'                  => 'O email deve ser um texto.',
            'email.email'                   => 'Introduz um email válido.',
            'email.max'                     => 'O email pode ter no máximo 255 caracteres.',
            'phone.string'                  => 'O telefone deve ser um texto.',
            'phone.max'                     => 'O telefone pode ter no máximo 20 caracteres.',
            'password.required'             => 'A senha é obrigatória.',
            'password.string'               => 'A senha deve ser um texto.',
            'password.min'                  => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed'            => 'A confirmação da senha não corresponde.',
            'password_confirmation.required_with' => 'A confirmação da senha é obrigatória.',
            'password_confirmation.string'  => 'A confirmação da senha deve ser um texto.',
            'password_confirmation.min'     => 'A confirmação da senha deve ter pelo menos 8 caracteres.',
            'role.in'                       => 'O tipo de conta é inválido.',
            'province_id.exists'            => 'A província selecionada é inválida.',
            'city_id.exists'                => 'A cidade selecionada é inválida.',
        ];

        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|max:255',
            'phone'                 => 'nullable|string|max:20',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required_with:password|string|min:8',
            'role'                  => 'nullable|in:customer,store_owner',
            'province_id'           => 'nullable|exists:provinces,id',
            'city_id'               => 'nullable|exists:cities,id',
        ], $messages);

        $existingUser = User::where('email', $validated['email'])->first();

        if ($existingUser) {
            if ($existingUser->email_verified) {
                return response()->json([
                    'message' => 'Este email já está registado. Usa outro email ou faz login.',
                ], 422);
            }

            if (!empty($validated['phone'])) {
                $phoneOwner = User::where('phone', $validated['phone'])
                    ->where('id', '!=', $existingUser->id)
                    ->first();

                if ($phoneOwner) {
                    return response()->json([
                        'message' => 'Este número de telefone já está a ser usado por outra conta.',
                    ], 422);
                }
            }

            $existingUser->update([
                'name'           => $validated['name'],
                'password'       => $validated['password'],
                'phone'          => $validated['phone'] ?? $existingUser->phone,
                'role'           => $validated['role'] ?? $existingUser->role ?? 'customer',
                'province_id'    => $validated['province_id'] ?? $existingUser->province_id,
                'city_id'        => $validated['city_id'] ?? $existingUser->city_id,
                'email_verified' => false,
                'is_active'      => false,
            ]);

            $this->sendOtp($existingUser);

            return response()->json([
                'requires_otp' => true,
                'email'        => $existingUser->email,
                'message'      => 'Conta já registada anteriormente mas ainda não confirmada. Enviámos um novo código para ' . $existingUser->email,
            ], 200);
        }

        if (!empty($validated['phone']) && User::where('phone', $validated['phone'])->exists()) {
            return response()->json([
                'message' => 'Este número de telefone já está a ser usado por outra conta.',
            ], 422);
        }

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
    private function buildUserPayload(User $user): array
    {
        $user->load(['province', 'city', 'neighborhood', 'store', 'activePosEmployee.store']);
        $userData = $user->toArray();
        unset($userData['active_pos_employee']);

        $userData['pos_employee'] = null;
        if ($posEmployee = $user->activePosEmployee) {
            $employeeData = $posEmployee->toArray();
            $employeeData['store'] = $posEmployee->store ? [
                'id'      => $posEmployee->store->id,
                'name'    => $posEmployee->store->name,
                'slug'    => $posEmployee->store->slug,
                'status'  => $posEmployee->store->status,
                'address' => $posEmployee->store->address,
            ] : null;
            $employeeData['pos_access_options'] = ['customer', 'pos'];
            $userData['pos_employee'] = $employeeData;
        }

        return $userData;
    }

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

        /** @var User $user */
        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            return response()->json(['message' => 'Conta suspensa. Contacte o suporte.'], 403);
        }

        $token = $user->createToken('beconnect-app')->plainTextToken;
        $userData = $this->buildUserPayload($user);

        return response()->json([
            'user'  => $userData,
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
            $this->buildUserPayload($user)
        );

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
