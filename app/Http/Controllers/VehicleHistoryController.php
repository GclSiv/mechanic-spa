<?php

namespace App\Http\Controllers;

use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\Setting;
use App\Models\Vehicle;
use Carbon\Carbon;
use Inertia\Inertia;

class VehicleHistoryController extends Controller
{
    /**
     * Historial de visitas de un vehículo: solo órdenes reparadas o entregadas.
     * Las refacciones no generan IVA; la mano de obra sí (regla del taller).
     */
    public function show(Vehicle $vehicle)
    {
        $vehicle->load(['brand', 'vehicleModel', 'client']);

        $statusIds    = RepairOrderStatus::whereIn('slug', ['reparado', 'entregado'])->pluck('id');
        $tasaRespaldo = (float) Setting::current()->iva;

        $visitas = RepairOrder::query()
            ->whereIn('status_id', $statusIds)
            ->whereHas('recepcion', fn ($q) => $q->where('vehicle_id', $vehicle->id))
            ->with(['recepcion', 'items.part', 'payments'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (RepairOrder $order) use ($tasaRespaldo) {
                $tasa = $order->tax_rate !== null ? (float) $order->tax_rate : $tasaRespaldo;

                $conceptos = $order->items->map(function ($item) use ($tasa) {
                    $importe  = round((float) $item->subtotal, 2);
                    $impuesto = $item->type === 'labor' ? round($importe * $tasa / 100, 2) : 0.0;

                    return [
                        'codigo'      => $item->part?->sku ?? ($item->type === 'labor' ? 'MO' : null),
                        'descripcion' => $item->description ?: ($item->part?->name ?? ''),
                        'cantidad'    => (float) $item->quantity,
                        'importe'     => $importe,
                        'impuesto'    => $impuesto,
                        'total'       => round($importe + $impuesto, 2),
                    ];
                })->values();

                $fecha  = $order->delivery_date ?? $order->updated_at;
                $total  = round($conceptos->sum('total'), 2);
                $pagado = round((float) $order->payments->sum('amount'), 2);
                $saldo  = round(max($total - $pagado, 0), 2);

                return [
                    'folio'     => $order->folio,
                    'fecha'     => $fecha ? Carbon::parse($fecha)->format('d/m/Y') : null,
                    'odometro'  => $order->recepcion?->miles,
                    'conceptos' => $conceptos,
                    'importe'   => round($conceptos->sum('importe'), 2),
                    'impuestos' => round($conceptos->sum('impuesto'), 2),
                    'total'     => $total,
                    'pagado'   => $pagado,
                    'saldo'    => $saldo,
                ];
            })
            ->values();

        return Inertia::render('Vehicles/History', [
            'vehicle' => [
                'id'     => $vehicle->id,
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
