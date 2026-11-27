<?php

namespace App\Http\Controllers;

use App\Models\AppointmentRequest;
use App\Models\Client;
use App\Models\ServiceReminder;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Pantalla administrativa de citas y próximos servicios (solo admin/owner por ruta). */
class AppointmentRequestController extends Controller
{
    private const ACCIONES = [
        'confirmar' => AppointmentRequest::CONFIRMADA,
        'rechazar'  => AppointmentRequest::RECHAZADA,
        'cancelar'  => AppointmentRequest::CANCELADA,
        'completar' => AppointmentRequest::COMPLETADA,
    ];

    public function index(): Response
    {
        $solicitudes = AppointmentRequest::with(['client', 'vehicle.brand', 'vehicle.vehicleModel', 'repairOrder'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (AppointmentRequest $c) => [
                'id'             => $c->id,
                'cliente'        => $this->nombreCliente($c->client),
                'telefono'       => $c->client?->phone,
                'vehiculo'       => $this->textoVehiculo($c->vehicle),
                'folio'          => $c->repairOrder?->folio,
                'motivo'         => $c->reason,
                'fecha'          => $c->preferred_date?->toDateString(),
                'horario'        => $c->preferred_time,
                'comentario'     => $c->notes,
                'creada'         => $c->created_at?->toDateTimeString(),
                'estado'         => $c->status,
                'confirmed_date' => $c->confirmed_date?->toDateString(),
                'confirmed_time' => $c->confirmed_time,
                'customer_note'  => $c->customer_note,
                'admin_notes'    => $c->admin_notes,
            ])
            ->values();

        $recordatorios = ServiceReminder::with(['client', 'vehicle.brand', 'vehicle.vehicleModel'])
            ->where('status', ServiceReminder::PENDIENTE)
            ->orderBy('recommended_date')
            ->get()
            ->map(fn (ServiceReminder $r) => [
                'id'          => $r->id,
                'titulo'      => $r->title,
                'cliente'     => $this->nombreCliente($r->client),
                'vehiculo'    => $this->textoVehiculo($r->vehicle),
                'fecha'       => $r->recommended_date?->toDateString(),
                'kilometraje' => $r->recommended_mileage,
            ])
            ->values();

        return Inertia::render('Citas/Index', [
            'solicitudes'   => $solicitudes,
            'recordatorios' => $recordatorios,
            'motivos'       => AppointmentRequest::MOTIVOS,
        ]);
    }

    public function update(Request $request, AppointmentRequest $cita): RedirectResponse
    {
        $datos = $request->validate([
            'accion'         => ['required', 'string', 'in:' . implode(',', array_keys(self::ACCIONES))],
            'confirmed_date' => ['required_if:accion,confirmar', 'nullable', 'date', 'after_or_equal:today'],
            'confirmed_time' => ['required_if:accion,confirmar', 'nullable', 'date_format:H:i'],
            'customer_note'  => ['nullable', 'string', 'max:500'],
            'admin_notes'    => ['nullable', 'string', 'max:1000'],
        ]);

        $nuevo = self::ACCIONES[$datos['accion']];
        abort_unless($cita->puedeTransicionarA($nuevo), 422, 'Transición de estado no permitida.');

        $cita->status = $nuevo;
        $cita->admin_notes = $datos['admin_notes'] ?? $cita->admin_notes;

        if ($nuevo === AppointmentRequest::CONFIRMADA) {
            $cita->confirmed_date = $datos['confirmed_date'];
            $cita->confirmed_time = $datos['confirmed_time'];
            $cita->customer_note = $datos['customer_note'] ?? null;
        }

        $cita->save();

        return back();
    }

    private function nombreCliente(?Client $cliente): ?string
    {
        return $cliente ? trim($cliente->first_name . ' ' . $cliente->last_name) : null;
    }

    private function textoVehiculo(?Vehicle $vehiculo): ?string
    {
        if (! $vehiculo) {
            return null;
        }

        $marca  = $vehiculo->getRelation('brand')?->name ?? (is_string($vehiculo->getAttribute('brand')) ? $vehiculo->getAttribute('brand') : null);
        $modelo = $vehiculo->getRelation('vehicleModel')?->name ?? (is_string($vehiculo->getAttribute('model')) ? $vehiculo->getAttribute('model') : null);

        return trim(implode(' ', array_filter([$marca, $modelo, $vehiculo->plate])));
    }
}
