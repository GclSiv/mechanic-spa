<?php

namespace App\Http\Controllers;

use App\Models\RepairOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RepairOrderController extends Controller
{
    /**
     * Muestra la vista de gestión para JK AUTOMOTIVE CARE INC.
     */
    public function show($id): Response
    {
        // Cargamos la orden con sus items para que se vean en la tabla de Vue
        $orden = RepairOrder::with(['recepcion', 'items'])->findOrFail($id);

    return inertia('RepairOrders/Show', [
        'orden' => $orden,
        'recepcion' => $orden->recepcion
    ]);
    }
}