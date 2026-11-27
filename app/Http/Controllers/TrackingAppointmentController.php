<?php

namespace App\Http\Controllers;

use App\Models\AppointmentRequest;
use App\Models\RepairOrder;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Solicitud de cita desde el seguimiento público (token = autorización).
 * Cliente, vehículo y orden salen del backend; el navegador solo envía preferencias.
 */
class TrackingAppointmentController extends Controller
{
    public function store(Request $request, string $token): RedirectResponse
    {
        $order = RepairOrder::where('tracking_token', $token)->firstOrFail();
        $recepcion = $order->recepcion;
        abort_unless($recepcion !== null, 404);

        $datos = $request->validate([
            'reason'         => ['required', 'string', 'in:' . implode(',', AppointmentRequest::MOTIVOS)],
            'preferred_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:' . now()->addDays(90)->toDateString()],
            'preferred_time' => ['nullable', 'string', 'in:' . implode(',', AppointmentRequest::HORARIOS)],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $creada = DB::transaction(function () use ($order, $recepcion, $datos): bool {
            // Bloqueo por vehículo: dos solicitudes simultáneas del mismo vehículo se serializan aquí (InnoDB, fila de vehicles).
            if ($recepcion->vehicle_id !== null) {
                Vehicle::query()->whereKey($recepcion->vehicle_id)->lockForUpdate()->first();
            }

            $activas = AppointmentRequest::query()->activas();
            if ($recepcion->vehicle_id !== null) {
                $activas->where('vehicle_id', $recepcion->vehicle_id);
            } else {
                $activas->where('repair_order_id', $order->id);
            }

            if ($activas->exists()) {
                return false;
            }

            AppointmentRequest::create([
                'client_id'       => $recepcion->client_id,
                'vehicle_id'      => $recepcion->vehicle_id,
                'repair_order_id' => $order->id,
                'preferred_date'  => $datos['preferred_date'],
                'preferred_time'  => $datos['preferred_time'] ?? null,
                'reason'          => $datos['reason'],
                'notes'           => $datos['notes'] ?? null,
                'status'          => AppointmentRequest::PENDIENTE,
            ]);

            return true;
        });

        if (! $creada) {
            // La página ya muestra la solicitud activa (cita.activa = true); no se crea otra.
            throw ValidationException::withMessages([
                'cita' => 'Ya tienes una solicitud de cita activa para tu vehículo. Te mostramos su estado abajo.',
            ]);
        }

        return back();
    }
}
