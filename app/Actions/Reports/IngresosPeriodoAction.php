<?php

namespace App\Actions\Reports;

use App\Models\Payment;
use App\Models\Quote;

/**
 * Reporte mensual de ingresos (FIN-002).
 *
 * INGRESO = dinero efectivamente cobrado: pagos válidos registrados en el periodo.
 * - Fecha contable: payments.created_at (no existe paid_at; la tabla payments no tiene soft deletes:
 *   un pago eliminado deja de existir y no se cuenta).
 * - Pagos generales (quote_id nulo) y anticipos antes de autorizar cuentan en el mes en que se registraron.
 * - No cuentan conceptos técnicos, autorizados sin pago, pendientes ni rechazados: no son dinero cobrado.
 * - No filtra por estado de la orden: la fuente es el pago.
 */
class IngresosPeriodoAction
{
    public function execute(int $month, int $year): array
    {
        $pagos = Payment::query()
            ->with([
                'quote:id,kind,folio',
                'repairOrder.recepcion.client',
                'repairOrder.recepcion.vehicle.brand',
                'repairOrder.recepcion.vehicle.vehicleModel',
            ])
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $rows = $pagos->map(function (Payment $pago) {
            $orden = $pago->repairOrder;
            $cliente = $orden?->recepcion?->client;
            $vehiculo = $orden?->recepcion?->vehicle;

            return [
                'fecha'    => $pago->created_at?->format('d/m/Y') ?? '—',
                'folio'    => $orden?->folio ?? ('Q-' . str_pad((string) $pago->repair_order_id, 4, '0', STR_PAD_LEFT)),
                'cliente'  => trim(($cliente?->first_name ?? '') . ' ' . ($cliente?->last_name ?? '')) ?: '—',
                'vehiculo' => trim(
                    ($vehiculo?->brand?->name ?? '') . ' ' .
                    ($vehiculo?->vehicleModel?->name ?? '') . ' ' .
                    ($vehiculo?->year ?? '')
                ) ?: '—',
                'tipo'     => $this->tipo($pago),
                'metodo'   => $pago->payment_method,
                'monto'    => round((float) $pago->amount, 2),
            ];
        })->values();

        $ingresos  = round($rows->sum('monto'), 2);
        $generales = round($rows->where('tipo', 'general')->sum('monto'), 2);

        return [
            'rows'   => $rows,
            'totals' => [
                'ingresos'        => $ingresos,
                'pagos'           => $rows->count(),
                'ordenes'         => $pagos->pluck('repair_order_id')->unique()->count(),
                'generales'       => $generales,
                'asociados'       => round($ingresos - $generales, 2),
            ],
        ];
    }

    private function tipo(Payment $pago): string
    {
        if ($pago->quote_id === null) {
            return 'general';
        }

        return $pago->quote?->kind === Quote::KIND_ADICIONAL ? 'adicional' : 'principal';
    }
}
