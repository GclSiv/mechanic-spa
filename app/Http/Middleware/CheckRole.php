<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * - "admin" lo cumplen admin y dueño.
     * - "owner" lo cumple solo el dueño.
     * - cualquier otro rol debe coincidir exactamente.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        $allowed = match ($role) {
            User::ROLE_ADMIN => $user?->isAdmin() ?? false,
            User::ROLE_OWNER => $user?->isOwner() ?? false,
            default          => $user?->role === $role,
        };

        abort_unless($allowed, 403);

        return $next($request);
    }
}
