<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    /**
     * Expediente integral del cliente: solo administrador y dueño.
     * El mecánico no accede (no ve datos fiscales, de contacto ni globales del cliente).
     */
    public function view(User $user, Client $client): bool
    {
        return $user->isAdmin();
    }
}
