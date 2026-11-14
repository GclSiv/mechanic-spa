<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Registrar pagos: solo administrador (el panel de pagos ya se muestra solo a admin).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Eliminar pagos: solo administrador.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }
}
