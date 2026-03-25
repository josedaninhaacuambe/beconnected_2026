<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminStaffController extends Controller
{
    const VALID_PERMISSIONS = [
        'manage_deliveries', 'manage_orders', 'manage_visibility',
        'manage_users', 'manage_stores', 'manage_commissions'
    ];

    public function index()
    {
        $staff = User::where('role', 'admin')
            ->whereNotNull('admin_role')
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'admin_role', 'permissions', 'is_active', 'created_at']);
        return response()->json($staff);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:8',
            'permissions'   => 'required|array',
            'permissions.*' => 'in:' . implode(',', self::VALID_PERMISSIONS),
        ]);

        $user = User::create([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'password'    => Hash::make($data['password']),
            'role'        => 'admin',
            'admin_role'  => 'staff',
            'permissions' => $data['permissions'],
            'is_active'   => true,
            'phone'       => $request->phone ?? '000000000',
        ]);

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->admin_role === null, 403, 'Não podes editar um admin completo.');
        $data = $request->validate([
            'name'          => 'sometimes|string|max:100',
            'email'         => 'sometimes|email|unique:users,email,' . $user->id,
            'permissions'   => 'sometimes|array',
            'permissions.*' => 'in:' . implode(',', self::VALID_PERMISSIONS),
        ]);
        $user->update($data);
        return response()->json($user);
    }

    public function toggleStatus(User $user)
    {
        abort_if($user->admin_role === null, 403, 'Não podes suspender um admin completo.');
        $user->update(['is_active' => !$user->is_active]);
        return response()->json(['is_active' => $user->is_active]);
    }

    public function destroy(User $user)
    {
        abort_if($user->admin_role === null, 403, 'Não podes eliminar um admin completo.');
        abort_if($user->id === auth()->id(), 403, 'Não podes eliminar a tua própria conta.');
        $user->delete();
        return response()->json(null, 204);
    }
}
