<?php

namespace App\Http\Controllers;

use App\Actions\RepairOrders\EstadoFinancieroAction;
use App\Models\Mechanic;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Support\ConteoOrdenes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Lista de órdenes con filtros y paginación, para llevar el control completo del taller
 * (cuando hay muchos vehículos o flotillas, el tablero Kanban deja de ser práctico).
 */
class RepairOrderListController extends Controller
{
    public function index(Request $request)
    {
        $filtros = [
            'buscar'     => trim((string) $request->input('buscar', '')),
            'estado'     => (string) $request->input('estado', ''),
            'mecanico'   => (string) $request->input('mecanico', ''),
            'saldo'      => (string) $request->input('saldo', ''),
            'desde'      => (string) $request->input('desde', ''),
            'hasta'      => (string) $request->input('hasta', ''),
        ];

        $query = RepairOrder::query()
            ->with(['status', 'mechanic', 'payments', 'recepcion.client', 'recepcion.vehicle.brand', 'recepcion.vehicle.vehicleModel'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($filtros['buscar'] !== '') {
            $b = $filtros['buscar'];
            $query->where(function (Builder $q) use ($b) {
                $q->where('folio', 'like', "%{$b}%")
                  // Identificador: VIN legado (vin) y el identificador nuevo por valor (VIN completo, últimos 8/10, serie, otro).
                  // Se busca por el valor, nunca por el tipo.
                  ->orWhereHas('recepcion.vehicle', fn ($v) => $v->where('plate', 'like', "%{$b}%")
                      ->orWhere('vin', 'like', "%{$b}%")
                      ->orWhere('identifier_value', 'like', "%{$b}%"))
                  ->orWhereHas('recepcion.client', fn ($c) => $c->where('first_name', 'like', "%{$b}%")
                      ->orWhere('last_name', 'like', "%{$b}%")->orWhere('phone', 'like', "%{$b}%"));
            });
        }

        if ($filtros['estado'] !== '') {
            $query->whereHas('status', fn ($s) => $s->where('slug', $filtros['estado']));
        }

        if ($filtros['mecanico'] !== '') {
            $query->where('mechanic_id', (int) $filtros['mecanico']);
        }

        // Saldo autorizado = total aprobado (snapshots) − pagos. Misma regla que EstadoFinancieroAction.
        $autorizadoSql = "COALESCE((SELECT SUM(q.total) FROM quotes q WHERE q.repair_order_id = repair_orders.id AND q.status = 'aprobada'), 0)";
        $pagadoSql     = 'COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.repair_order_id = repair_orders.id), 0)';
        if ($filtros['saldo'] === 'pendiente') {
            $query->whereRaw("({$autorizadoSql} - {$pagadoSql}) > 0");
        } elseif ($filtros['saldo'] === 'liquidado') {
            $query->whereRaw("({$autorizadoSql} - {$pagadoSql}) <= 0");
        }

        if ($filtros['desde'] !== '') {
            $query->whereDate('created_at', '>=', $filtros['desde']);
        }
        if ($filtros['hasta'] !== '') {
            $query->whereDate('created_at', '<=', $filtros['hasta']);
        }

        $ordenes = $query->paginate(25)->withQueryString();

        $estado = app(EstadoFinancieroAction::class);

        $filas = $ordenes->through(function (RepairOrder $o) use ($estado) {
            $financiero = $estado->execute($o);
            $vehiculo = $o->recepcion?->vehicle;

            return [
                'id'         => $o->id,
                'folio'      => $o->folio ?? 'Q-' . str_pad($o->id, 4, '0', STR_PAD_LEFT),
                'placa'      => $vehiculo?->plate,
                'vehiculo'   => trim(($vehiculo?->brand?->name ?? '') . ' ' . ($vehiculo?->vehicleModel?->name ?? '') . ' ' . ($vehiculo?->year ?? '')) ?: '—',
                'cliente'    => trim(($o->recepcion?->client?->first_name ?? '') . ' ' . ($o->recepcion?->client?->last_name ?? '')) ?: '—',
                'telefono'   => $o->recepcion?->client?->phone,
                'mecanico'   => $o->mechanic?->name ?? '—',
                'estado'     => $o->status?->name ?? '—',
                'estado_slug'=> $o->status?->slug,
                'color'      => $o->status?->color_class ?? 'bg-gray-100 text-gray-600',
                'total'      => $financiero['total_autorizado'],
                'pagado'     => $financiero['total_pagado'],
                'saldo'      => $financiero['saldo_autorizado'],
                'pendiente'  => $financiero['pendiente_autorizacion'],
                'entrada'    => $o->created_at?->format('d/m/Y'),
                'dias'       => $o->created_at ? (int) $o->created_at->diffInDays(now()) : null,
            ];
        });

        return Inertia::render('RepairOrders/Lista', [
            'ordenes'   => $filas,
            'filtros'   => $filtros,
            'estados'   => RepairOrderStatus::orderBy('id')->get(['slug', 'name']),
            'mecanicos' => Mechanic::orderBy('name')->get(['id', 'name']),
            // Indicadores del encabezado: conteos del taller completo (no dependen de los filtros).
            'resumen'              => ConteoOrdenes::flujo(null),
            'autorizacionPendiente' => ConteoOrdenes::esperandoAutorizacion(),
        ]);
    }
}
