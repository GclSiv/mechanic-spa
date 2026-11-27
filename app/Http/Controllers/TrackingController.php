<?php

namespace App\Http\Controllers;

use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Models\Quote;
use App\Models\RepairOrderPhoto;
use App\Models\RepairOrder;
use App\Models\Setting;
use App\Models\Vehicle;
use App\Models\ServiceReminder;
use App\Models\AppointmentRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Página pública de seguimiento. Solo expone lo necesario para el cliente:
 * sin teléfono, RFC, dirección ni notas internas.
 */
use App\Actions\RepairOrders\EstadoFinancieroAction;

class TrackingController extends Controller
{
    public function show(string $token, EstadoFinancieroAction $estado)
    {
        $order = $this->findOrder($token);
        $order->load(['payments', 'quotes']);

        $recepcion = $order->recepcion;
        $vehicle   = $recepcion?->vehicle;
        $quote     = $order->currentQuote();
        $slug      = $order->status?->slug;
        $settings  = Setting::current();

        // Cifras autorizadas: lo que el cliente ya aprobó, lo pagado y su saldo
        $financiero = $estado->execute($order);
        $total      = $financiero['total_autorizado'];
        $pagado     = $financiero['total_pagado'];
        $saldo      = $financiero['saldo_autorizado'];

        // Pendientes: lo que el cliente tiene que hacer, redactado en el servidor
        // Orden cerrada: la cotización enviada es histórica, no una acción pendiente del cliente
        $cerrada    = $order->estaCerrada();
        $pendientes = [];
        $enviadas   = $cerrada ? collect() : $order->quotes->where('status', Quote::ENVIADA);
        foreach ($enviadas as $pendiente) {
            $pendientes[] = ['texto' => $pendiente->isExpired()
                ? 'La cotización ' . $pendiente->folio . ' venció. Pide una nueva al taller.'
                : 'Revisa y aprueba la cotización ' . $pendiente->folio . ' para que el taller continúe.'];
        }
        if ($saldo > 0 && in_array($slug, ['reparado', 'entregado'], true)) {
            $pendientes[] = ['texto' => 'Tienes un saldo pendiente de $' . number_format($saldo, 2) . ' por liquidar en el taller.'];
        }
        if ($slug === 'reparado') {
            $pendientes[] = ['texto' => 'Tu vehículo está listo para recoger.'];
        }

        // Línea de tiempo: 5 etapas. Espera de refacciones y en reparación caen en "Reparación".
        $indexActual = match ($slug) {
            'recibido'                    => 0,
            'diagnostico'                 => 1,
            'espera-piezas', 'en-reparacion' => 2,
            'reparado'                    => 3,
            'entregado'                   => 4,
            default                       => -1,
        };

        $etapas = [
            ['key' => 'recibido',   'label' => 'Recibido'],
            ['key' => 'diagnostico','label' => 'Diagnóstico'],
            ['key' => 'reparacion', 'label' => 'Reparación'],
            ['key' => 'reparado',   'label' => 'Listo'],
            ['key' => 'entregado',  'label' => 'Entregado'],
        ];
        $steps = collect($etapas)->map(fn ($e, $i) => $e + [
            'done'    => $i < $indexActual,
            'current' => $i === $indexActual,
        ])->values();

        $mensajes = [
            'recibido'      => 'Recibimos tu vehículo y lo estamos revisando.',
            'diagnostico'   => 'Estamos haciendo el diagnóstico de tu vehículo.',
            'espera-piezas' => 'Esperamos la llegada de refacciones para continuar.',
            'en-reparacion' => 'Tu vehículo está en reparación.',
            'reparado'      => 'Tu vehículo está listo. Puedes pasar a recogerlo.',
            'entregado'     => '¡Gracias por tu preferencia!',
        ];

        return Inertia::render('Tracking/Show', [
            'orden' => [
                'folio'   => $order->folio,
                'cerrada' => $cerrada,
                // Si la orden perdió su estado, el cliente ve "En proceso" en vez de un espacio vacío
                'status'  => [
                    'slug'  => $slug,
                    'name'  => $order->status?->name ?? 'En proceso',
                    'color' => $order->status?->color_class ?? 'bg-gray-500',
                ],
                'entry_date' => ($fechaIngreso = $order->entry_date ?? $recepcion?->created_at)
                    ? \Carbon\Carbon::parse($fechaIngreso)->toDateString()
                    : null,
                'delivery_date' => $order->delivery_date
                    ? \Carbon\Carbon::parse($order->delivery_date)->toDateString()
                    : null,
            ],
            'client_first_name' => $recepcion?->client?->first_name,
            'vehicle' => [
                'brand' => $vehicle?->brand?->name ?? $vehicle?->brand,
                'model' => $vehicle?->vehicleModel?->name ?? $vehicle?->model,
                'year'  => $vehicle?->year,
                'plate' => $vehicle?->plate,
                'color' => $vehicle?->color,
            ],
            'timeline' => [
                'rechazado' => $slug === 'rechazado',
                'steps'     => $steps,
                'mensaje'   => $mensajes[$slug] ?? 'Estamos trabajando en tu vehículo.',
            ],
            'pendientes'         => $pendientes,
            'financiero'         => ['total' => $total, 'pagado' => $pagado, 'saldo' => $saldo],
            'pendiente_autorizacion' => $financiero['pendiente_autorizacion'],
            'ultima_actualizacion' => optional($order->updated_at)->format('d/m/Y H:i'),
            'avance_fotos' => $order->avancePhotos->map(fn ($f) => [
                'id'    => $f->id,
                'note'  => $f->note,
                'fecha' => optional($f->created_at)->format('d/m/Y'),
                'url'   => route('tracking.avance.photo', ['token' => $token, 'photo' => $f->id]),
            ])->values(),
            'quotes' => $this->cotizacionesPublicas($order, $token, $cerrada),
            'current_quote_id' => $quote?->id,
            'cita' => $this->citaReciente($order, $vehicle),
            'recordatorios' => $this->recordatoriosVehiculo($vehicle),
            'token' => $token,
            'settings' => [
                'company_name' => $settings->company_name,
                'phone'        => $settings->phone,
                'whatsapp'     => $settings->whatsapp,
            ],
        ]);
    }

    /**
     * Descarga el PDF de una cotización de la orden (solo con el token correcto).
     */
    public function quotePdf(string $token, Quote $quote)
    {
        $order = $this->findOrder($token);
        abort_unless($quote->repair_order_id === $order->id, 404);
        // Un borrador nunca se muestra al cliente: solo cotizaciones enviadas o posteriores
        abort_if($quote->status === Quote::BORRADOR, 404);

        $quote->load('items');
        $settings = Setting::current();

        $pdf = Pdf::loadView('pdf.cotizacion-folio', [
            'quote'     => $quote,
            'order'     => $order,
            'recepcion' => $order->recepcion,
            'settings'  => $settings,
        ]);

        return $pdf->stream('Cotizacion_' . Str::slug($quote->folio) . '.pdf');
    }

    /**
     * El cliente acepta una cotización enviada. Exige nombre, casilla y la misma huella
     * que vio en pantalla, para no aceptar un contenido distinto al que se le mostró.
     */
    public function accept(Request $request, string $token, Quote $quote)
    {
        $order = $this->findOrder($token);
        abort_unless($quote->repair_order_id === $order->id, 404);
        abort_if($quote->status === Quote::BORRADOR, 404);

        // Orden cerrada = histórica: el enlace público ya no puede aprobar nada.
        if ($order->estaCerrada()) {
            return back()->withErrors(['quote' => 'Esta orden ya está cerrada. La cotización ya no se puede aceptar.']);
        }

        if ($quote->status !== Quote::ENVIADA || $quote->isExpired()) {
            return back()->withErrors(['quote' => 'Esta cotización ya no se puede aceptar.']);
        }

        $data = $request->validate([
            'nombre' => 'required|string|min:5|max:120',
            'acepto' => 'accepted',
            'hash'   => 'required|string|size:64',
        ], [
            'nombre.min'  => 'Escribe tu nombre completo.',
            'acepto.accepted' => 'Marca la casilla para aceptar la cotización.',
        ]);

        $quote->load('items');

        if (! hash_equals($quote->snapshotHash(), $data['hash'])) {
            return back()->withErrors(['quote' => 'La cotización cambió desde que la abriste. Recarga la página para verla actualizada.']);
        }

        try {
            $quote->acceptByClient(
                $data['nombre'],
                $request->ip(),
                substr((string) $request->userAgent(), 0, 255),
                $data['hash'],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors(['quote' => $e->validator->errors()->first('status')]);
        }

        return back()->with('success', 'Cotización aceptada. ¡Gracias!');
    }

    /**
     * El cliente rechaza una cotización enviada, con un motivo opcional.
     */
    public function reject(Request $request, string $token, Quote $quote)
    {
        $order = $this->findOrder($token);
        abort_unless($quote->repair_order_id === $order->id, 404);
        abort_if($quote->status === Quote::BORRADOR, 404);

        if ($order->estaCerrada()) {
            return back()->withErrors(['quote' => 'Esta orden ya está cerrada. La cotización ya no se puede rechazar.']);
        }

        if ($quote->status !== Quote::ENVIADA) {
            return back()->withErrors(['quote' => 'Esta cotización ya no se puede rechazar.']);
        }

        $data = $request->validate(['motivo' => 'nullable|string|max:255']);

        $quote->reject($data['motivo'] ?? null);

        return back()->with('success', 'Cotización rechazada. El taller te contactará.');
    }

    /**
     * Foto del avance, solo con el token correcto de la orden.
     */
    public function avancePhoto(string $token, RepairOrderPhoto $photo)
    {
        $order = $this->findOrder($token);
        abort_unless($photo->repair_order_id === $order->id, 404);

        return \App\Support\ArchivoPrivado::respuesta($photo->path, basename((string) $photo->path));
    }

    /**
     * Cita que muestra el seguimiento: la activa del vehículo si existe (aunque sea de otra orden
     * del mismo vehículo); si no, la más reciente de esta orden. Sin notas internas (admin_notes).
     */
    private function citaReciente(RepairOrder $order, ?Vehicle $vehicle): ?array
    {
        $activa = $vehicle
            ? AppointmentRequest::query()->activas()->where('vehicle_id', $vehicle->id)->latest()->first()
            : null;
        $cita = $activa ?? AppointmentRequest::where('repair_order_id', $order->id)->latest()->first();
        if (! $cita) {
            return null;
        }

        return [
            'activa'           => $cita->estaActiva(),
            'estado'           => $cita->status,
            'motivo'           => $cita->reason,
            'fecha'            => $cita->preferred_date?->toDateString(),
            'horario'          => $cita->preferred_time,
            'confirmada_fecha' => $cita->confirmed_date?->toDateString(),
            'confirmada_hora'  => $cita->confirmed_time,
            'nota_taller'      => $cita->customer_note,
        ];
    }

    /** Próximos servicios pendientes del vehículo, solo con campos visibles para el cliente. */
    private function recordatoriosVehiculo(?Vehicle $vehicle): array
    {
        if (! $vehicle) {
            return [];
        }

        return ServiceReminder::where('vehicle_id', $vehicle->id)
            ->where('status', ServiceReminder::PENDIENTE)
            ->orderBy('recommended_date')
            ->get()
            ->map(fn (ServiceReminder $r) => [
                'titulo'      => $r->title,
                'fecha'       => $r->recommended_date?->toDateString(),
                'kilometraje' => $r->recommended_mileage,
                'nota'        => $r->notes,
            ])
            ->values()
            ->all();
    }

    private function findOrder(string $token): RepairOrder
    {
        return RepairOrder::where('tracking_token', $token)
            ->with(['status', 'recepcion.client', 'recepcion.vehicle.brand', 'recepcion.vehicle.vehicleModel'])
            ->firstOrFail();
    }

    /**
     * Cotizaciones públicas de la orden: exactamente el mismo conjunto de siempre (todo lo que no es borrador).
     * Solo se añade la clasificación para la UI:
     * - "visible": aprobadas (principal o adicional) y la enviada vigente más reciente (la que requiere decisión).
     * - "historico": reemplazadas, rechazadas, vencidas y enviadas anteriores.
     * Los items se cargan en una sola consulta (sin N+1).
     */
    private function cotizacionesPublicas(RepairOrder $order, string $token, bool $cerrada): \Illuminate\Support\Collection
    {
        $cotizaciones = $order->quotes()
            ->where('status', '!=', Quote::BORRADOR)
            ->with('items')
            ->get();

        $enviadaVigente = $cotizaciones
            ->filter(fn (Quote $q) => $q->status === Quote::ENVIADA && ! $q->isExpired())
            ->sortByDesc('id')
            ->first();

        return $cotizaciones->map(function (Quote $q) use ($token, $cerrada, $enviadaVigente) {
            $resumen = $this->quoteSummary($q, $token, $cerrada);
            $visible = $q->status === Quote::APROBADA
                || ($enviadaVigente !== null && $q->id === $enviadaVigente->id);
            $esEnviadaVigente = $enviadaVigente !== null && $q->id === $enviadaVigente->id;
            $resumen['bloque'] = $visible ? 'visible' : 'historico';
            // Prioridad visual determinista: 0 enviada vigente, 1 aprobada principal, 2 aprobada adicional, 3 historica.
            $resumen['prioridad'] = match (true) {
                $esEnviadaVigente => 0,
                $q->status === Quote::APROBADA && $q->kind === Quote::KIND_PRINCIPAL => 1,
                $q->status === Quote::APROBADA => 2,
                default => 3,
            };

            return $resumen;
        })->sortBy([['prioridad', 'asc'], ['id', 'asc']])->values();
    }

    private function quoteSummary(Quote $quote, string $token, bool $cerrada): array
    {
        $quote->loadMissing('items');

        return [
            'id'          => $quote->id,
            'folio'       => $quote->folio,
            'version'     => $quote->version,
            'status'      => $quote->status,
            'kind'        => $quote->kind,
            'hash'        => $quote->snapshotHash(),
            'can_accept'  => $quote->status === Quote::ENVIADA && ! $quote->isExpired() && ! $cerrada,
            'accepted_name' => $quote->client_accepted_name ?? $quote->approved_by_name,
            'accepted_at'   => optional($quote->approved_at)->format('d/m/Y H:i'),
            'rejection_reason' => $quote->rejection_reason,
            'valid_until' => optional($quote->valid_until)->toDateString(),
            'expired'     => $quote->isExpired(),
            'parts_subtotal' => $quote->parts_subtotal,
            'labor_subtotal' => $quote->labor_subtotal,
            'subtotal'    => $quote->subtotal,
            'tax_rate'    => $quote->tax_rate,
            'tax'         => $quote->tax,
            'total'       => $quote->total,
            'notes'       => $quote->notes,
            'items'       => $quote->items->map(fn ($i) => [
                'type'        => $i->type,
                'description' => $i->description,
                'quantity'    => $i->quantity,
                'unit_price'  => $i->unit_price,
                'subtotal'    => $i->subtotal,
            ])->values(),
            'pdf_url'     => route('tracking.quote.pdf', ['token' => $token, 'quote' => $quote->id]),
        ];
    }
}
