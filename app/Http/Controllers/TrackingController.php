<?php

namespace App\Http\Controllers;

use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Models\Quote;
use App\Models\RepairOrderPhoto;
use App\Models\RepairOrder;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Página pública de seguimiento. Solo expone lo necesario para el cliente:
 * sin teléfono, RFC, dirección ni notas internas.
 */
class TrackingController extends Controller
{
    public function show(string $token, CalculateOrderTotalsAction $calculator)
    {
        $order = $this->findOrder($token);
        $order->load(['payments', 'quotes']);

        $recepcion = $order->recepcion;
        $vehicle   = $recepcion?->vehicle;
        $quote     = $order->currentQuote();
        $slug      = $order->status?->slug;
        $settings  = Setting::current();

        // Totales y saldo (solo cifras que el cliente ya puede ver: total, pagado y saldo)
        $breakdown = $calculator->execute($order);
        $total     = round((float) $breakdown['total'], 2);
        $pagado    = round((float) $order->payments->sum('amount'), 2);
        $saldo     = round(max($total - $pagado, 0), 2);

        // Pendientes: lo que el cliente tiene que hacer, redactado en el servidor
        $pendientes = [];
        foreach ($order->quotes->where('status', Quote::ENVIADA) as $pendiente) {
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
            'ultima_actualizacion' => optional($order->updated_at)->format('d/m/Y H:i'),
            'avance_fotos' => $order->avancePhotos->map(fn ($f) => [
                'id'    => $f->id,
                'note'  => $f->note,
                'fecha' => optional($f->created_at)->format('d/m/Y'),
                'url'   => route('tracking.avance.photo', ['token' => $token, 'photo' => $f->id]),
            ])->values(),
            'quotes' => $order->quotes()->where('status', '!=', Quote::BORRADOR)->get()->map(fn (Quote $q) => $this->quoteSummary($q, $token))->values(),
            'current_quote_id' => $quote?->id,
            'token' => $token,
            'settings' => [
                'company_name' => $settings->company_name,
                'phone'        => $settings->phone,
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

        $quote->acceptByClient(
            $data['nombre'],
            $request->ip(),
            substr((string) $request->userAgent(), 0, 255),
            $data['hash'],
        );

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

        return Storage::disk('public')->response($photo->path);
    }

    private function findOrder(string $token): RepairOrder
    {
        return RepairOrder::where('tracking_token', $token)
            ->with(['status', 'recepcion.client', 'recepcion.vehicle.brand', 'recepcion.vehicle.vehicleModel'])
            ->firstOrFail();
    }

    private function quoteSummary(Quote $quote, string $token): array
    {
        $quote->load('items');

        return [
            'id'          => $quote->id,
            'folio'       => $quote->folio,
            'version'     => $quote->version,
            'status'      => $quote->status,
            'kind'        => $quote->kind,
            'hash'        => $quote->snapshotHash(),
            'can_accept'  => $quote->status === Quote::ENVIADA && ! $quote->isExpired(),
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
