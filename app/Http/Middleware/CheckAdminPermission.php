<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Não autenticado.'], 401);
        // Full admins (admin_role is null) bypass all permission checks
        if ($user->admin_role === null) return $next($request);
        $permissions = $user->permissions ?? [];
        if (!in_array($permission, $permissions)) {
            return response()->json(['message' => 'Sem permissão para esta acção.'], 403);
        }
        return $next($request);
    }
}
