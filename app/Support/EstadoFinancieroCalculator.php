<?php

namespace App\Support;

use App\Models\Quote;
use Illuminate\Support\Carbon;

/**
 * Única fuente de las reglas financieras de A2 (función pura, sin base de datos).
 *
 * Recibe los datos ya cargados de UNA orden y devuelve su estado financiero.
 * La usan EstadoFinancieroAction::execute() y el lector en lote
 * (EstadosFinancierosOrdenesAction). No copiar estas fórmulas en otro lugar.
 *
 * - total_autorizado: suma del snapshot de cotizaciones APROBADAS (no se recalcula).
 * - pendiente_autorizacion: IVA por orden sobre conceptos sin billed_quote_id.
 * - saldo_autorizado: max(autorizado - pagado, 0), por orden.
 * - sobrepago: max(pagado - autorizado, 0), solo cuando hay autorización.
 */
final class EstadoFinancieroCalculator
{
    /**
     * @param  float  $tasa  tasa de IVA de la orden, ya resuelta con TaxCalculator::resolveRate
     * @param  array{part: float, labor: float}  $tecnico  subtotales de todos los conceptos
     * @param  array{part: float, labor: float}  $sinAutorizar  subtotales de conceptos sin billed_quote_id
     * @param  list<array{id: int, folio: ?string, kind: string, status: string, total: float, approved_at: ?Carbon}>  $cotizaciones  enviada/aprobada/rechazada, ordenadas por kind y version
     * @param  list<array{quote_id: ?int, amount: float, created_at: ?Carbon}>  $pagos
     */
    public static function calcular(float $tasa, array $tecnico, array $sinAutorizar, array $cotizaciones, array $pagos): array
    {
        $desgloseTecnico   = TaxCalculator::compute($tecnico['part'], $tecnico['labor'], $tasa);
        $desglosePendiente = TaxCalculator::compute($sinAutorizar['part'], $sinAutorizar['labor'], $tasa);

        $aprobadas = array_values(array_filter($cotizaciones, fn ($q) => $q['status'] === Quote::APROBADA));

        $autorizado = round(array_sum(array_column($aprobadas, 'total')), 2);
        $pagado     = round(array_sum(array_column($pagos, 'amount')), 2);
        $saldo      = round(max($autorizado - $pagado, 0), 2);

        $pendienteAutorizacion = round((float) $desglosePendiente['total'], 2);

        // Anticipos: pagos anteriores a la primera aprobación (todos, si todavía no hay aprobación).
        // LIMITACIÓN CONOCIDA (comportamiento preexistente, pendiente de fase posterior):
        // approved_at y payments.created_at tienen precisión de segundos. Un pago y una aprobación
        // del mismo segundo no se pueden ordenar, y el pago cuenta como anticipo. Afecta solo a la
        // clasificación informativa de anticipo_previo; no altera saldo, sobrepago ni reglas de entrega.
        $primera = self::primeraAprobacion($aprobadas);
        $anticipoPrevio = $primera === null
            ? $pagado
            : round(array_sum(array_column(array_filter(
                $pagos,
                fn ($p) => $p['created_at'] !== null && $p['created_at']->lte($primera),
            ), 'amount')), 2);

        return [
            'total_tecnico'          => round((float) $desgloseTecnico['total'], 2),
            'total_autorizado'       => $autorizado,
            'pendiente_autorizacion' => $pendienteAutorizacion,
            'total_pagado'           => $pagado,
            'saldo_autorizado'       => $saldo,
            // Pagado por encima de lo autorizado. Solo existe con autorización; no hay saldo a favor.
            'sobrepago'              => $autorizado > 0 ? round(max($pagado - $autorizado, 0), 2) : 0.0,
            'anticipo_previo'        => $anticipoPrevio,
            'liquidado'              => $saldo <= 0 && $pendienteAutorizacion <= 0,
            'puede_entregarse'       => $saldo <= 0 && $pendienteAutorizacion <= 0,
            'tasa'                   => $tasa,
            'cotizaciones'           => self::cotizaciones($cotizaciones, $pagos),
            'pago_sin_cotizacion'    => round(array_sum(array_column(array_filter(
                $pagos,
                fn ($p) => $p['quote_id'] === null,
            ), 'amount')), 2),
        ];
    }

    /**
     * Primera fecha de aprobación. Si alguna aprobada no tiene fecha, el resultado es null
     * (así lo hacía A2 con ORDER BY approved_at, donde NULL va primero).
     */
    private static function primeraAprobacion(array $aprobadas): ?Carbon
    {
        if ($aprobadas === []) {
            return null;
        }

        $primera = null;
        foreach (array_column($aprobadas, 'approved_at') as $fecha) {
            if ($fecha === null) {
                return null;
            }
            if ($primera === null || $fecha->lt($primera)) {
                $primera = $fecha;
            }
        }

        return $primera;
    }

    private static function cotizaciones(array $cotizaciones, array $pagos): array
    {
        $porCotizacion = [];
        foreach ($pagos as $p) {
            if ($p['quote_id'] !== null) {
                $porCotizacion[$p['quote_id']] = ($porCotizacion[$p['quote_id']] ?? 0) + $p['amount'];
            }
        }

        $resultado = [];
        foreach ($cotizaciones as $q) {
            $total  = round((float) $q['total'], 2);
            $pagado = round((float) ($porCotizacion[$q['id']] ?? 0), 2);

            $resultado[] = [
                'id'     => $q['id'],
                'folio'  => $q['folio'],
                'kind'   => $q['kind'],
                'status' => $q['status'],
                'total'  => $total,
                'pagado' => $pagado,
                'saldo'  => round(max($total - $pagado, 0), 2),
            ];
        }

        return $resultado;
    }
}
