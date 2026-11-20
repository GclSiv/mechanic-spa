<?php

namespace App\Actions\RepairOrders;

use App\Models\Quote;
use App\Models\RepairOrder;
use App\Support\TaxCalculator;

/**
 * Fuente única del estado financiero de una orden (A2).
 *
 * - total_tecnico: conceptos actuales de la orden (CalculateOrderTotalsAction::calcular).
 * - total_autorizado: suma del total congelado (snapshot) de las cotizaciones APROBADAS.
 * - pendiente_autorizacion: conceptos sin billed_quote_id, con IVA vía TaxCalculator.
 * - total_pagado: todos los pagos de la orden (quote_id es solo atribución).
 * - saldo_autorizado: max(total_autorizado - total_pagado, 0). Es la deuda exigible del cliente.
 *
 * estimated_cost NO es deuda exigible: es el total técnico.
 * is_paid es solo caché: se sincroniza aquí y no debe usarse para decidir.
 */
class EstadoFinancieroAction
{
    public function __construct(private CalculateOrderTotalsAction $calculator) {}

    public function execute(RepairOrder $order): array
    {
        $tecnico = $this->calculator->calcular($order); // no persiste
        $tasa    = (float) $tecnico['tax_rate'];

        $autorizado = round((float) Quote::where('repair_order_id', $order->id)
            ->where('status', Quote::APROBADA)
            ->sum('total'), 2);

        // Conceptos que todavía no están en ninguna cotización aprobada
        $sinAutorizar = $order->items()->whereNull('billed_quote_id')->get(['type', 'subtotal']);
        $pendiente = TaxCalculator::compute(
            (float) $sinAutorizar->where('type', 'part')->sum('subtotal'),
            (float) $sinAutorizar->where('type', 'labor')->sum('subtotal'),
            $tasa,
        );

        // Pagos: una sola consulta. Todos cuentan contra el saldo autorizado, tengan o no quote_id.
        $pagos  = $order->payments()->get(['quote_id', 'amount', 'created_at']);
        $pagado = round((float) $pagos->sum('amount'), 2);
        $saldo  = round(max($autorizado - $pagado, 0), 2);

        $pendienteAutorizacion = round((float) $pendiente['total'], 2);

        // Anticipos previos: pagos registrados antes de la primera aprobación (o todos, si aún no hay aprobación)
        $primeraAprobacion = Quote::where('repair_order_id', $order->id)
            ->where('status', Quote::APROBADA)
            ->orderBy('approved_at')
            ->value('approved_at');
        $anticipoPrevio = $primeraAprobacion === null
            ? $pagado
            : round((float) $pagos->filter(fn ($p) => $p->created_at !== null
                && $p->created_at->lte(\Illuminate\Support\Carbon::parse($primeraAprobacion)))->sum('amount'), 2);

        return [
            'total_tecnico'          => round((float) $tecnico['total'], 2),
            'total_autorizado'       => $autorizado,
            'pendiente_autorizacion' => $pendienteAutorizacion,
            'total_pagado'           => $pagado,
            'saldo_autorizado'       => $saldo,
            // Pagado por encima de lo autorizado. Solo existe con autorización; no se implementa saldo a favor.
            'sobrepago'              => $autorizado > 0 ? round(max($pagado - $autorizado, 0), 2) : 0.0,
            'anticipo_previo'        => $anticipoPrevio,
            'liquidado'              => $saldo <= 0 && $pendienteAutorizacion <= 0,
            'puede_entregarse'       => $saldo <= 0 && $pendienteAutorizacion <= 0,
            'tasa'                   => $tasa,
            'cotizaciones'           => $this->cotizaciones($order, $pagos),
            'pago_sin_cotizacion'    => round((float) $pagos->whereNull('quote_id')->sum('amount'), 2),
        ];
    }

    /**
     * Ejecuta el cálculo persistente de totales (estimated_cost) y sincroniza is_paid como caché.
     */
    public function sincronizar(RepairOrder $order): array
    {
        $this->calculator->execute($order);

        $estado = $this->execute($order);

        if ((bool) $order->is_paid !== $estado['liquidado']) {
            $order->update(['is_paid' => $estado['liquidado']]);
        }

        return $estado;
    }

    private function cotizaciones(RepairOrder $order, $pagos): array
    {
        $porCotizacion = $pagos->whereNotNull('quote_id')
            ->groupBy('quote_id')
            ->map(fn ($grupo) => round((float) $grupo->sum('amount'), 2));

        return Quote::where('repair_order_id', $order->id)
            ->whereIn('status', [Quote::ENVIADA, Quote::APROBADA, Quote::RECHAZADA])
            ->orderBy('kind')
            ->orderBy('version')
            ->get(['id', 'folio', 'kind', 'status', 'total'])
            ->map(function (Quote $q) use ($porCotizacion) {
                $total  = round((float) $q->total, 2);
                $pagado = round((float) ($porCotizacion[$q->id] ?? 0), 2);

                return [
                    'id'     => $q->id,
                    'folio'  => $q->folio,
                    'kind'   => $q->kind,
                    'status' => $q->status,
                    'total'  => $total,
                    'pagado' => $pagado,
                    'saldo'  => round(max($total - $pagado, 0), 2),
                ];
            })
            ->values()
            ->all();
    }
}
