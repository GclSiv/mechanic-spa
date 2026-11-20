<?php

namespace App\Http\Controllers;

use App\Models\RepairOrder;
use App\Models\Setting;
use App\Models\Vehicle;
use Carbon\Carbon;
use App\Support\TaxCalculator;
use Inertia\Inertia;

class VehicleHistoryController extends Controller
{
    /**
     * Historial de visitas de un vehículo: todas las órdenes, excepto las rechazadas.
     * Las que aún no están reparadas o entregadas se marcan como "en proceso".
     * Las refacciones no generan IVA; la mano de obra sí (regla del taller).
     */
    public function show(Vehicle $vehicle)
    {
        $vehicle->load(['brand', 'vehicleModel', 'client']);

        $calculator = app(\App\Actions\RepairOrders\CalculateOrderTotalsAction::class);
        $estado     = app(\App\Actions\RepairOrders\EstadoFinancieroAction::class);

        $visitas = RepairOrder::query()
            ->whereHas('status', fn ($q) => $q->where('slug', '!=', 'rechazado'))
            ->whereHas('recepcion', fn ($q) => $q->where('vehicle_id', $vehicle->id))
            ->with(['recepcion', 'items.part', 'payments', 'status'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (RepairOrder $order) use ($calculator, $estado) {
                // Total técnico con la misma fórmula que la orden (IVA sobre refacciones y mano de obra)
                $desglose = $calculator->calcular($order);
                $tasa     = (float) $desglose['tax_rate'];

                // Cada renglón usa la misma función fiscal (TaxCalculator), sin fórmula propia
                $conceptos = $order->items->map(function ($item) use ($tasa) {
                    $importe = round((float) $item->subtotal, 2);
                    $linea   = TaxCalculator::compute(
                        $item->type === 'part' ? $importe : 0.0,
                        $item->type === 'labor' ? $importe : 0.0,
                        $tasa,
                    );

                    return [
                        'codigo'      => $item->part?->sku ?? ($item->type === 'labor' ? 'MO' : null),
                        'descripcion' => $item->description ?: ($item->part?->name ?? ''),
                        'cantidad'    => (float) $item->quantity,
                        'importe'     => $linea['subtotal'],
                        'impuesto'    => $linea['tax'],
                        'total'       => $linea['total'],
                    ];
                })->values();

                $fecha      = $order->delivery_date ?? $order->updated_at;
                $financiero = $estado->execute($order);

                return [
                    'id'        => $order->id,
                    'folio'     => $order->folio,
                    'estado'    => $order->status?->name ?? '—',
                    'terminado' => in_array($order->status?->slug, ['reparado', 'entregado'], true),
                    'ultimo_pago_id' => $order->payments->sortBy('id')->last()?->id,
                    'fecha'     => $fecha ? Carbon::parse($fecha)->format('d/m/Y') : null,
                    'odometro'  => $order->recepcion?->miles,
                    'conceptos' => $conceptos,
                    'importe'   => $desglose['subtotal'],
                    'impuestos' => $desglose['tax'],
                    'total'     => $desglose['total'],
                    'autorizado' => $financiero['total_autorizado'],
                    'pendiente' => $financiero['pendiente_autorizacion'],
                    'pagado'    => $financiero['total_pagado'],
                    'saldo'     => $financiero['saldo_autorizado'],
                ];
            })
            ->values();

        return Inertia::render('Vehicles/History', [
            'vehicle' => [
                'id'        => $vehicle->id,
                'client_id' => $vehicle->client_id,
                'brand'  => $vehicle->brand?->name ?? $vehicle->brand,
                'model'  => $vehicle->vehicleModel?->name ?? $vehicle->model,
                'year'   => $vehicle->year,
                'plate'  => $vehicle->plate,
                'vin'    => $vehicle->vin,
                'client' => trim(($vehicle->client?->first_name ?? '') . ' ' . ($vehicle->client?->last_name ?? '')),
            ],
            'visitas' => $visitas,
        ]);
    }
}
