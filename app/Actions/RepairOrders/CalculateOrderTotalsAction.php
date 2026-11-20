<?php

namespace App\Actions\RepairOrders;

use App\Models\RepairOrder;
use App\Models\Setting;
use App\Support\TaxCalculator;
use Illuminate\Support\Facades\DB;

class CalculateOrderTotalsAction
{
    /**
     * Calcula y persiste la tasa y el total de la orden (estimated_cost).
     */
    public function execute(RepairOrder $repairOrder): array
    {
        $breakdown = $this->calcular($repairOrder);

        $repairOrder->update([
            'tax_rate'       => $breakdown['tax_rate'],
            'estimated_cost' => $breakdown['total'],
        ]);

        return $breakdown;
    }

    /**
     * Desglose de subtotal, IVA y total de la orden SIN persistir nada.
     * Es la única fórmula: pantalla, saldo, PDF y exportación deben usar este método.
     */
    public function calcular(RepairOrder $repairOrder): array
    {
        // 1. Sumas por tipo directamente desde la base de datos
        $items = $repairOrder->items()
            ->select('type', DB::raw('SUM(subtotal) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $partsTotal = (float) ($items['part'] ?? 0);
        $laborTotal = (float) ($items['labor'] ?? 0);

        // 2. La tasa queda fijada en la orden. Si aún no tiene, toma la configuración vigente.
        //    Así un cambio futuro en settings no altera cotizaciones anteriores.
        //    Regla compartida con el lector en lote: TaxCalculator::resolveRate.
        $rate = TaxCalculator::resolveRate(
            $repairOrder->tax_rate !== null ? (float) $repairOrder->tax_rate : null,
            $repairOrder->tax_rate === null ? (float) Setting::current()->iva : null,
        );

        // 3. Desglose fiscal (IVA general 16% o zona fronteriza 8% sobre todo el concepto)
        return TaxCalculator::compute($partsTotal, $laborTotal, $rate);
    }
}
