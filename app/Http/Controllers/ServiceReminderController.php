<?php

namespace App\Http\Controllers;

use App\Models\RepairOrder;
use App\Models\ServiceReminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Programa el próximo servicio de un vehículo desde su orden (solo admin/owner por ruta). */
class ServiceReminderController extends Controller
{
    public function store(Request $request, RepairOrder $order): RedirectResponse
    {
        $recepcion = $order->recepcion;
        abort_unless($recepcion !== null, 404);

        $datos = $request->validate([
            'title'              => ['required', 'string', 'max:120'],
            'recommended_date'   => ['nullable', 'date', 'after:today', 'before_or_equal:' . now()->addYears(3)->toDateString()],
            'recommended_mileage' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'notes'              => ['nullable', 'string', 'max:500'],
        ]);

        abort_if(
            empty($datos['recommended_date']) && empty($datos['recommended_mileage']),
            422,
            'Indica una fecha, un kilometraje o ambos.'
        );

        ServiceReminder::create([
            'client_id'           => $recepcion->client_id,
            'vehicle_id'          => $recepcion->vehicle_id,
            'repair_order_id'     => $order->id,
            'title'               => $datos['title'],
            'recommended_date'    => $datos['recommended_date'] ?? null,
            'recommended_mileage' => $datos['recommended_mileage'] ?? null,
            'notes'               => $datos['notes'] ?? null,
            'status'              => ServiceReminder::PENDIENTE,
        ]);

        return back();
    }
}
