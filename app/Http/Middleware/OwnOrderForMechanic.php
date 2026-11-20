<?php

namespace App\Http\Middleware;

use App\Models\RepairOrder;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permite al mecánico modificar solo las órdenes que tiene asignadas.
 * El administrador y el dueño pasan sin restricción. Las consultas (GET) no se restringen aquí.
 */
class OwnOrderForMechanic
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isAdmin() || $request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        abort_unless($user->role === User::ROLE_MECHANIC, 403);

        $order      = $request->route('order');
        $mechanicId = $user->mechanic?->id;

        abort_unless(
            $order instanceof RepairOrder && $mechanicId && (int) $order->mechanic_id === (int) $mechanicId,
            403,
            'Solo puedes modificar las órdenes que tienes asignadas.'
        );

        return $next($request);
    }
}
