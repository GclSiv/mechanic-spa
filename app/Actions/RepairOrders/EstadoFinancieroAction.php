<?php

namespace App\Actions\RepairOrders;

use App\Models\RepairOrder;

/**
 * Fuente única del estado financiero de una orden (A2).
 *
 * Las reglas viven en App\Support\EstadoFinancieroCalculator; los datos se cargan con
 * EstadosFinancierosOrdenesAction. Esta clase conserva la firma original para los consumidores.
 *
 * - total_tecnico: conceptos actuales de la orden (CalculateOrderTotalsAction::calcular).
 * - total_autorizado: suma del total congelado (snapshot) de las cotizaciones APROBADAS.
 * - pendiente_autorizacion: conceptos sin billed_quote_id, con IVA por orden.
 * - total_pagado: todos los pagos de la orden (quote_id es solo atribución).
 * - saldo_autorizado: max(total_autorizado - total_pagado, 0). Es la deuda exigible del cliente.
 *
 * estimated_cost NO es deuda exigible: es el total técnico.
 * is_paid es solo caché: se sincroniza aquí y no debe usarse para decidir.
 */
class EstadoFinancieroAction
{
    public function __construct(
        private CalculateOrderTotalsAction $calculator,
        private EstadosFinancierosOrdenesAction $lector,
    ) {}

    /**
     * Estado financiero de la instancia recibida. Se pasa la instancia (no el ID) para conservar la
     * semántica previa: su tax_rate en memoria es la fuente de la tasa, igual que en CalculateOrderTotalsAction::calcular.
     * Nunca lanza una excepción nueva: el lector siempre devuelve una entrada para una instancia.
     */
    public function execute(RepairOrder $order): array
    {
        return $this->lector->execute([$order])[(int) $order->id];
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
}
