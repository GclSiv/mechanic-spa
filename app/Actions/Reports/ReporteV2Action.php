<?php

namespace App\Actions\Reports;

use App\Actions\RepairOrders\EstadosFinancierosOrdenesAction;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reportes V2: indicadores de un rango de fechas. Fuentes existentes; no se recalcula nada en paralelo.
 *
 * - Ingresos cobrados: payments.created_at dentro del rango (FIN-002: sin soft deletes, un pago eliminado
 *   no existe; una cotización aprobada sin pago no suma).
 * - Trabajo autorizado: cotizaciones APROBADAS con approved_at en el rango. Es el snapshot que A2 suma
 *   en total_autorizado. No es ingreso.
 * - Saldo pendiente: saldo_autorizado de A2 para órdenes NO cerradas, al corte de hoy (no depende del rango).
 * - Órdenes creadas: repair_orders.created_at en el rango. Reparadas/entregadas: conteo por ESTADO ACTUAL de esas
 *   órdenes (no hay fecha de transición en el sistema; no son conteos del periodo).
 */
class ReporteV2Action
{
    /** Hasta 62 días se agrupa por día; más largo, por mes. */
    private const UMBRAL_DIAS_AGRUPACION = 62;

    /** Límite de filas de la tabla de órdenes; el resto se indica como truncado. */
    private const LIMITE_ORDENES = 300;

    public function __construct(private EstadosFinancierosOrdenesAction $lector) {}

    public function execute(Carbon $desde, Carbon $hasta, ?int $estadoId = null, ?int $mecanicoId = null): array
    {
        $inicio = $desde->copy()->startOfDay();
        $fin = $hasta->copy()->endOfDay();

        $pagos = $this->pagos($inicio, $fin);
        $ingresos = round((float) $pagos->sum('amount'), 2);

        $autorizadas = Quote::query()
            ->where('status', Quote::APROBADA)
            ->whereBetween('approved_at', [$inicio, $fin])
            ->get(['id', 'repair_order_id', 'kind', 'parts_subtotal', 'labor_subtotal', 'tax', 'total']);

        $saldoPendiente = $this->saldoPendienteActivas();

        $ordenes = $this->ordenes($inicio, $fin, $estadoId, $mecanicoId);
        $conteos = $this->conteosOrdenes($inicio, $fin);
        $clientes = $this->clientes($inicio, $fin);

        return [
            'indicadores' => [
                'ingresos_cobrados' => $ingresos,
                'pagos' => $pagos->count(),
                'trabajo_autorizado' => round((float) $autorizadas->sum('total'), 2),
                'saldo_pendiente' => $saldoPendiente,
                'ordenes_creadas' => $conteos['creadas'],
                'ordenes_en_proceso' => $conteos['en_proceso'],
                'ordenes_reparadas_actual' => $conteos['reparadas_actual'],
                'ordenes_entregadas_actual' => $conteos['entregadas_actual'],
                'clientes_atendidos' => $clientes['atendidos'],
                'clientes_recurrentes' => $clientes['recurrentes'],
                'vehiculos_atendidos' => $clientes['vehiculos'],
            ],
            'desglose' => [
                'refacciones' => round((float) $autorizadas->sum('parts_subtotal'), 2),
                'mano_obra' => round((float) $autorizadas->sum('labor_subtotal'), 2),
                'iva' => round((float) $autorizadas->sum('tax'), 2),
                'total_autorizado' => round((float) $autorizadas->sum('total'), 2),
                'total_cobrado' => $ingresos,
                'saldo_pendiente' => $saldoPendiente,
            ],
            'evolucion' => $this->evolucion($pagos, $desde, $hasta),
            'pagos' => $pagos->map(fn (Payment $p) => $this->filaPago($p))->values()->all(),
            'ordenes' => $ordenes,
        ];
    }

    /** Saldo exigible de las órdenes no cerradas, tomado del lector A2 (misma fórmula, sin copia). */
    private function saldoPendienteActivas(): float
    {
        $cerrados = RepairOrderStatus::whereIn('slug', RepairOrderStatus::CERRADOS)->pluck('id');

        $abiertas = RepairOrder::query()->whereNotIn('status_id', $cerrados)->get();
        if ($abiertas->isEmpty()) {
            return 0.0;
        }

        return round(array_sum(array_column($this->lector->execute($abiertas), 'saldo_autorizado')), 2);
    }

    private function pagos(Carbon $inicio, Carbon $fin)
    {
        return Payment::query()
            ->with([
                'quote:id,kind,folio',
                'repairOrder:id,folio,recepcion_id',
                'repairOrder.recepcion.client:id,first_name,last_name',
            ])
            ->whereBetween('created_at', [$inicio, $fin])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    private function filaPago(Payment $pago): array
    {
        $orden = $pago->repairOrder;
        $cliente = $orden?->recepcion?->client;

        return [
            'fecha' => $pago->created_at?->format('d/m/Y H:i'),
            'orden' => $orden?->folio,
            'cliente' => trim(($cliente?->first_name ?? '') . ' ' . ($cliente?->last_name ?? '')) ?: null,
            'concepto' => $pago->quote_id === null
                ? 'Pago general'
                : trim(($pago->quote?->kind === Quote::KIND_ADICIONAL ? 'Trabajo adicional' : 'Cotización') . ' ' . ($pago->quote?->folio ?? '')),
            'metodo' => $pago->payment_method,
            'monto' => round((float) $pago->amount, 2),
        ];
    }

    /** Ingresos agrupados por día (rango corto) o por mes (rango largo). */
    private function evolucion($pagos, Carbon $desde, Carbon $hasta): array
    {
        $porDia = $desde->diffInDays($hasta) <= self::UMBRAL_DIAS_AGRUPACION;

        return $pagos
            ->groupBy(fn (Payment $p) => $porDia ? $p->created_at->format('Y-m-d') : $p->created_at->format('Y-m'))
            ->map(fn ($grupo, $clave) => [
                'periodo' => (string) $clave,
                'pagos' => $grupo->count(),
                'monto' => round((float) $grupo->sum('amount'), 2),
            ])
            ->values()
            ->all();
    }

    private function ordenes(Carbon $inicio, Carbon $fin, ?int $estadoId, ?int $mecanicoId): array
    {
        $consulta = RepairOrder::query()
            ->with(['recepcion.client:id,first_name,last_name', 'recepcion.vehicle:id,plate', 'status:id,name', 'quotes'])
            ->whereBetween('created_at', [$inicio, $fin]);

        if ($estadoId !== null) {
            $consulta->where('status_id', $estadoId);
        }
        if ($mecanicoId !== null) {
            $consulta->where('mechanic_id', $mecanicoId);
        }

        $ordenes = $consulta->orderByDesc('created_at')->limit(self::LIMITE_ORDENES)->get();
        $estados = $ordenes->isEmpty() ? [] : $this->lector->execute($ordenes);

        return $ordenes->map(function (RepairOrder $o) use ($estados) {
            $a2 = $estados[(int) $o->id] ?? [];
            $aprobadas = $o->quotes->where('status', Quote::APROBADA);
            $cliente = $o->recepcion?->client;

            return [
                'folio' => $o->folio,
                'fecha' => $o->created_at?->format('d/m/Y'),
                'cliente' => trim(($cliente?->first_name ?? '') . ' ' . ($cliente?->last_name ?? '')) ?: null,
                'vehiculo' => $o->recepcion?->vehicle?->plate,
                'estado' => $o->status?->name,
                'refacciones' => round((float) $aprobadas->sum('parts_subtotal'), 2),
                'mano_obra' => round((float) $aprobadas->sum('labor_subtotal'), 2),
                'iva' => round((float) $aprobadas->sum('tax'), 2),
                'total_autorizado' => round((float) ($a2['total_autorizado'] ?? 0), 2),
                'pagado' => round((float) ($a2['total_pagado'] ?? 0), 2),
                'saldo' => round((float) ($a2['saldo_autorizado'] ?? 0), 2),
            ];
        })->values()->all();
    }

    /**
     * Conteos de órdenes CREADAS en el rango, por ESTADO ACTUAL.
     * El sistema no guarda la fecha de cada transición (no hay bitácora de estados ni repaired_at/delivered_at;
     * delivery_date no se escribe), así que "reparadas" y "entregadas" no son conteos del periodo.
     * En_proceso = creadas en el rango que hoy no están en reparado, entregado ni rechazado.
     */
    private function conteosOrdenes(Carbon $inicio, Carbon $fin): array
    {
        $tabla = (new RepairOrderStatus)->getTable();

        $porEstado = DB::table('repair_orders')
            ->join($tabla, "$tabla.id", '=', 'repair_orders.status_id')
            ->whereBetween('repair_orders.created_at', [$inicio, $fin])
            ->groupBy("$tabla.slug")
            ->select("$tabla.slug", DB::raw('COUNT(*) as n'))
            ->pluck('n', 'slug');

        $total = (int) $porEstado->sum();
        $enProceso = $total - (int) ($porEstado['reparado'] ?? 0) - (int) ($porEstado['entregado'] ?? 0) - (int) ($porEstado['rechazado'] ?? 0);

        return [
            'creadas' => $total,
            'en_proceso' => max($enProceso, 0),
            'reparadas_actual' => (int) ($porEstado['reparado'] ?? 0),
            'entregadas_actual' => (int) ($porEstado['entregado'] ?? 0),
        ];
    }

    /** Clientes y vehículos de las órdenes creadas en el rango; recurrentes = clientes con 2+ órdenes en total. */
    private function clientes(Carbon $inicio, Carbon $fin): array
    {
        $filas = DB::table('repair_orders')
            ->join('recepcions', 'recepcions.id', '=', 'repair_orders.recepcion_id')
            ->whereBetween('repair_orders.created_at', [$inicio, $fin])
            ->select('recepcions.client_id', 'recepcions.vehicle_id')
            ->get();

        $clientesIds = $filas->pluck('client_id')->filter()->unique()->values();

        $recurrentes = 0;
        if ($clientesIds->isNotEmpty()) {
            $recurrentes = DB::table('repair_orders')
                ->join('recepcions', 'recepcions.id', '=', 'repair_orders.recepcion_id')
                ->whereIn('recepcions.client_id', $clientesIds->all())
                ->groupBy('recepcions.client_id')
                ->select('recepcions.client_id', DB::raw('COUNT(*) as n'))
                ->get()
                ->filter(fn ($fila) => (int) $fila->n > 1)
                ->count();
        }

        return [
            'atendidos' => $clientesIds->count(),
            'recurrentes' => $recurrentes,
            'vehiculos' => $filas->pluck('vehicle_id')->filter()->unique()->count(),
        ];
    }
}
