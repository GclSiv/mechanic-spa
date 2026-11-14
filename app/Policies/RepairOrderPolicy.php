<?php

namespace App\Policies;

use App\Models\RepairOrder;
use App\Models\User;

class RepairOrderPolicy
{
    /**
     * Eliminar una orden de reparación es exclusivo del administrador.
     * (Todavía no hay ruta de borrado; la política queda lista para cuando la haya.)
     */
    public function delete(User $user, RepairOrder $order): bool
    {
        return $user->isAdmin();
    }
}
