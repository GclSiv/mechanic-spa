<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\RepairOrder;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Página pública de seguimiento. Solo expone lo necesario para el cliente:
 * sin teléfono, RFC, dirección ni notas internas.
 */
class TrackingController extends Controller
{
    public function show(string $token)
    {
        $order = $this->findOrder($token);

        $recepcion = $order->recepcion;
        $vehicle   = $recepcion?->vehicle;
        $quote     = $order->currentQuote();

        return Inertia::render('Tracking/Show', [
            'orden' => [
                'folio'   => $order->folio,
                // Si la orden perdió su estado, el cliente ve "En proceso" en vez de un espacio vacío
                'status'  => [
                    'slug'  => $order->status?->slug,
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
            'quotes' => $order->quotes()->where('status', '!=', Quote::BORRADOR)->get()->map(fn (Quote $q) => $this->quoteSummary($q, $token))->values(),
            'current_quote_id' => $quote?->id,
            'settings' => [
                'company_name' => Setting::current()->company_name,
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
