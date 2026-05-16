<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuth
{
    public function handle(Request $request, Closure $next, ?string $role = null)
    {
        $user = Auth::guard('sanctum')->user() ?? Auth::user();
        if (! $user || empty($user->is_active)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        if ($role && ($user->role ?? null) !== $role && ! ($user->isOwner() ?? false)) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        return $next($request);
    }
}
