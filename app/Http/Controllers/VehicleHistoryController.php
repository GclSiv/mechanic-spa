<?php

namespace App\Http\Controllers;

use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Actions\RepairOrders\EstadoFinancieroAction;
use App\Models\Recepcion;
use App\Models\Vehicle;
use Inertia\Inertia;

/**
 * Expediente técnico de un vehículo (admin/owner; el mecánico recibe 403 por la ruta).
 *
 * - Visitas = recepciones del vehículo, tengan o no orden. Una recepción sin orden es una visita real.
 * - Diagnóstico: NO se muestra como campo propio. diagnosisComplete escribe un FollowUp con texto fijo
 *   y actualiza la orden (needs_quote), sin tipo ni relación que distinga el diagnóstico de una nota.
 *   Por eso el bloque se presenta como "Bitácora / notas técnicas".
 * - Órdenes rechazadas: la recepción se conserva; la orden aparece como rechazada, sin trabajos ni totales.
 * - Montos: solo vía CalculateOrderTotalsAction y EstadoFinancieroAction. Sin comisiones ni campos de comisión.
 */
class VehicleHistoryController extends Controller
{
    public function show(Vehicle $vehicle, CalculateOrderTotalsAction $calculator, EstadoFinancieroAction $estado)
    {
        $vehicle->load(['client:id,first_name,last_name,client_number', 'brand', 'vehicleModel']);

        $recepciones = $vehicle->recepciones()
            ->with(['repairOrder' => fn ($q) => $q->with([
                'status:id,name,slug,color_class',
                'mechanic:id,name',
                'items' => fn ($q) => $q
                    ->select(['id', 'repair_order_id', 'type', 'description', 'quantity', 'subtotal', 'part_id', 'mechanic_id'])
                    ->orderBy('id')
                    ->with(['mechanic:id,name', 'part:id,name,sku']),
                'followUps' => fn ($q) => $q->with('mechanic:id,name')->orderByDesc('id'),
            ])])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $visitas = $recepciones->map(fn (Recepcion $r) => $this->visita($r, $calculator, $estado))->values();

        $ultimaConKm = $recepciones->first(fn (Recepcion $r) => $this->kilometraje($r) !== null);
        $ultimaConOrden = $recepciones->first(fn (Recepcion $r) => $r->repairOrder !== null);

        $propietario = $vehicle->client
            ? ['id' => $vehicle->client->id, 'nombre' => trim($vehicle->client->first_name . ' ' . $vehicle->client->last_name)]
            : null;

        return Inertia::render('Vehicles/History', [
            'vehicle' => [
                'id'                   => $vehicle->id,
                'marca'                => $vehicle->displayBrand(),
                'modelo'               => $vehicle->displayModel(),
                'year'                 => $vehicle->year,
                'plate'                => $vehicle->plate,
                'vin'                  => $vehicle->vin ?: null,
                'propietario'          => $propietario,
                'nueva_recepcion_url'  => route('recepcion.create', [
                    'client_id'  => $vehicle->client_id,
                    'vehicle_id' => $vehicle->id,
                ]),
            ],
            'resumen' => [
                'visitas_totales'       => $recepciones->count(),
                'ultima_visita'         => $recepciones->first()?->created_at?->toIso8601String(),
                'ultima_recepcion_id'   => $recepciones->first()?->id,
                'ultimo_kilometraje'    => $ultimaConKm ? $this->kilometraje($ultimaConKm) : null,
                'estado_ultima_orden'   => $ultimaConOrden?->repairOrder?->status?->name,
                'estado_ultima_orden_slug' => $ultimaConOrden?->repairOrder?->status?->slug,
            ],
            'visitas' => $visitas,
        ]);
    }

    /** Kilometraje como entero si la recepción lo registró como número; null si está vacío o no es numérico. */
    private function kilometraje(Recepcion $r): ?int
    {
        $raw = trim((string) $r->miles);

        return $raw !== '' && is_numeric($raw) ? (int) $raw : null;
    }

    private function visita(Recepcion $r, CalculateOrderTotalsAction $calculator, EstadoFinancieroAction $estado): array
    {
        return [
            'id'          => $r->id,
            'numero'      => str_pad((string) $r->id, 4, '0', STR_PAD_LEFT),
            'fecha'       => $r->created_at?->toIso8601String(),
            'kilometraje' => $this->kilometraje($r),
            'problema'    => $r->symptoms ?: null,
            'orden'       => $r->repairOrder ? $this->orden($r->repairOrder, $calculator, $estado) : null,
        ];
    }

    private function orden($orden, CalculateOrderTotalsAction $calculator, EstadoFinancieroAction $estado): array
    {
        $slug = $orden->status?->slug;
        $base = [
            'id'        => $orden->id,
            'folio'     => $orden->folio,
            'estado'    => $orden->status?->name ?? '—',
            'slug'      => $slug,
            'rechazada' => $slug === 'rechazado',
            'terminada' => in_array($slug, ['reparado', 'entregado'], true),
            'mecanico'  => $orden->mechanic?->name,
        ];

        // Rechazada: la visita se conserva, pero no presenta trabajos ni montos como si se hubieran ejecutado.
        if ($base['rechazada']) {
            return $base + ['ejecutores' => [], 'trabajos' => [], 'refacciones' => [], 'notas' => [], 'finanzas' => null];
        }

        $labor = $orden->items->where('type', 'labor');
        $partes = $orden->items->where('type', 'part');

        $financiero = $estado->execute($orden);

        return $base + [
            'ejecutores'  => $labor->pluck('mechanic.name')->filter()->unique()->values()->all(),
            'trabajos'    => $labor->map(fn ($i) => [
                'descripcion' => $i->description,
                'cantidad'    => (float) $i->quantity,
                'ejecutor'    => $i->mechanic?->name,
            ])->values()->all(),
            'refacciones' => $partes->map(fn ($i) => [
                'descripcion' => $i->description ?: ($i->part?->name ?? ''),
                'codigo'      => $i->part?->sku,
                'cantidad'    => (float) $i->quantity,
            ])->values()->all(),
            'notas' => $orden->followUps->map(fn ($n) => [
                'fecha'  => $n->date ?? $n->created_at?->toDateTimeString(),
                'autor'  => $n->mechanic?->name,
                'texto'  => $n->description,
            ])->values()->all(),
            'finanzas' => [
                'total'    => $calculator->calcular($orden)['total'],
                'autorizado' => $financiero['total_autorizado'],
                'pagado'   => $financiero['total_pagado'],
                'saldo'    => $financiero['saldo_autorizado'],
            ],
        ];
    }
}
