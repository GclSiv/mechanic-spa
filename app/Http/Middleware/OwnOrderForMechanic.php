<?php

namespace App\Http\Middleware;

use App\Models\RepairOrder;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El mecánico solo puede consultar y modificar las órdenes que tiene asignadas (GET incluido).
 * El administrador y el dueño pasan sin restricción. Las rutas sin parámetro de orden
 * (por ejemplo, el tablero) se filtran en su controlador.
 */
class OwnOrderForMechanic
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isAdmin()) {
            return $next($request);
        }

        abort_unless($user->role === User::ROLE_MECHANIC, 403);

        $order = $request->route('order');

        if ($order === null) {
            return $next($request);
        }

        $mechanicId = $user->mechanic?->id;

        abort_unless(
            $order instanceof RepairOrder && $mechanicId && (int) $order->mechanic_id === (int) $mechanicId,
            403,
            'Solo puedes acceder a las órdenes que tienes asignadas.'
        );

        return $next($request);
    }
}
