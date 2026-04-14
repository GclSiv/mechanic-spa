<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if ($request->user()?->role !== $role) {
            return redirect()->route('dashboard')
                ->with('error', 'No tienes permisos para acceder a esa sección.');
        }

        return $next($request);
    }
}
