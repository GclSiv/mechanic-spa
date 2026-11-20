<?php

namespace App\Actions\RepairOrders;

use App\Models\Payment;
use App\Models\Quote;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\Setting;
use App\Support\EstadoFinancieroCalculator;
use App\Support\TaxCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Lector en lote del estado financiero (B1.2.1). Solo lectura: no persiste nada.
 *
 * CONTRATO
 * - Entrada: instancias de RepairOrder o IDs, en cualquier mezcla.
 * - Tasa: si se recibe una INSTANCIA, se usa su tax_rate en memoria (igual que
 *   CalculateOrderTotalsAction::calcular, que es la semántica previa de EstadoFinancieroAction).
 *   Si se recibe un ID, la tasa se lee de la base de datos. Una instancia tiene prioridad sobre
 *   un ID con el mismo valor.
 * - IDs inexistentes: se OMITEN del resultado. No lanzan excepción y no generan entrada.
 * - Una instancia se calcula aunque su registro ya no exista en BD: sin conceptos, cotizaciones
 *   ni pagos, igual que antes del refactor.
 * - Resultado: arreglo indexado por repair_order_id, en el orden de primera aparición de la entrada,
 *   sin duplicados.
 * - Consultas: con IDs son 4 (órdenes, conceptos, cotizaciones, pagos). Con instancias no se
 *   consultan las órdenes y son 3. Ninguna cantidad depende del número de órdenes.
 *
 * Las reglas no viven aquí: este lector solo recopila datos y delega en EstadoFinancieroCalculator.
 */
class EstadosFinancierosOrdenesAction
{
    private const SIN_FACTURAR = 'CASE WHEN billed_quote_id IS NULL THEN 1 ELSE 0 END';

    /**
     * @param  iterable<RepairOrder|int>  $ordenes  órdenes o sus IDs
     * @return array<int, array>  estado financiero indexado por repair_order_id
     */
    public function execute(iterable $ordenes): array
    {
        // 1. Normaliza la entrada: instancias (con su tasa en memoria) e IDs (tasa desde BD)
        $instancias = [];
        $ids        = [];

        foreach ($ordenes as $o) {
            $id = $o instanceof RepairOrder ? (int) $o->id : (int) $o;

            if ($o instanceof RepairOrder) {
                $instancias[$id] = $o;
            }

            $ids[$id] = $id; // conserva el orden de primera aparición y elimina duplicados
        }

        $ids = array_values($ids);

        if ($ids === []) {
            return [];
        }

        // 2. Tasa de cada orden. Las instancias no se releen; solo se consultan los IDs.
        $faltantes = array_values(array_filter($ids, fn ($id) => ! isset($instancias[$id])));

        $bd = $faltantes === []
            ? collect()
            : RepairOrder::query()->whereIn('id', $faltantes)->get(['id', 'tax_rate'])->keyBy('id');

        $tasaOrden = []; // id => tax_rate (?float); solo órdenes existentes o instancias recibidas
        foreach ($ids as $id) {
            if (isset($instancias[$id])) {
                $tasaOrden[$id] = $instancias[$id]->tax_rate !== null ? (float) $instancias[$id]->tax_rate : null;
            } elseif ($bd->has($id)) {
                $tasaOrden[$id] = $bd->get($id)->tax_rate !== null ? (float) $bd->get($id)->tax_rate : null;
            }
        }

        if ($tasaOrden === []) {
            return [];
        }

        $existentes = array_keys($tasaOrden);

        // 3. Tasa configurada: una sola consulta, y solo si alguna orden no tiene la suya
        $configurada = in_array(null, $tasaOrden, true)
            ? (float) Setting::current()->iva
            : null;

        // 4. Conceptos agrupados por orden, tipo y si ya están autorizados
        $conceptos = [];
        $filas = RepairOrderItem::query()
            ->whereIn('repair_order_id', $existentes)
            ->selectRaw('repair_order_id, type, SUM(subtotal) AS total, ' . self::SIN_FACTURAR . ' AS sin_facturar')
            ->groupBy('repair_order_id', 'type', DB::raw(self::SIN_FACTURAR))
            ->get();

        foreach ($filas as $fila) {
            if (! in_array($fila->type, ['part', 'labor'], true)) {
                continue;
            }

            $oid   = (int) $fila->repair_order_id;
            $total = (float) $fila->total;

            $conceptos[$oid]['tecnico'][$fila->type] = ($conceptos[$oid]['tecnico'][$fila->type] ?? 0.0) + $total;

            if ((int) $fila->sin_facturar === 1) {
                $conceptos[$oid]['sin'][$fila->type] = ($conceptos[$oid]['sin'][$fila->type] ?? 0.0) + $total;
            }
        }

        // 5. Cotizaciones (solo los estados que A2 usa)
        $cotizacionesPorOrden = [];
        $cotizaciones = Quote::query()
            ->whereIn('repair_order_id', $existentes)
            ->whereIn('status', [Quote::ENVIADA, Quote::APROBADA, Quote::RECHAZADA])
            ->orderBy('kind')
            ->orderBy('version')
            ->get(['id', 'repair_order_id', 'folio', 'kind', 'status', 'total', 'approved_at']);

        foreach ($cotizaciones as $q) {
            $cotizacionesPorOrden[(int) $q->repair_order_id][] = [
                'id'          => (int) $q->id,
                'folio'       => $q->folio,
                'kind'        => $q->kind,
                'status'      => $q->status,
                'total'       => (float) $q->total,
                'approved_at' => $q->approved_at,
            ];
        }

        // 6. Pagos (todos cuentan contra el saldo; quote_id solo es atribución)
        $pagosPorOrden = [];
        $pagos = Payment::query()
            ->whereIn('repair_order_id', $existentes)
            ->orderByDesc('created_at')
            ->get(['repair_order_id', 'quote_id', 'amount', 'created_at']);

        foreach ($pagos as $p) {
            $pagosPorOrden[(int) $p->repair_order_id][] = [
                'quote_id'   => $p->quote_id !== null ? (int) $p->quote_id : null,
                'amount'     => (float) $p->amount,
                'created_at' => $p->created_at,
            ];
        }

        // 7. Cálculo: una sola regla, por orden
        $resultado = [];
        foreach ($existentes as $id) {
            $resultado[$id] = EstadoFinancieroCalculator::calcular(
                TaxCalculator::resolveRate($tasaOrden[$id], $configurada),
                $this->desglose($conceptos[$id]['tecnico'] ?? []),
                $this->desglose($conceptos[$id]['sin'] ?? []),
                $cotizacionesPorOrden[$id] ?? [],
                $pagosPorOrden[$id] ?? [],
            );
        }

        return $resultado;
    }

    /** Garantiza las dos claves de tipo aunque la orden no tenga conceptos de ese tipo. */
    private function desglose(array $porTipo): array
    {
        return [
            'part'  => (float) ($porTipo['part'] ?? 0.0),
            'labor' => (float) ($porTipo['labor'] ?? 0.0),
        ];
    }
}
