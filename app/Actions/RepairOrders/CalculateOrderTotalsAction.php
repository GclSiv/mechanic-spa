<?php

namespace App\Actions\RepairOrders;

use App\Models\RepairOrder;
use App\Models\Setting;
use App\Support\TaxCalculator;
use Illuminate\Support\Facades\DB;

class CalculateOrderTotalsAction
{
    public function execute(RepairOrder $repairOrder): array
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
        $rate = $repairOrder->tax_rate !== null
            ? (float) $repairOrder->tax_rate
            : $this->configuredRate();

        if (! TaxCalculator::isValidRate($rate)) {
            $rate = TaxCalculator::GENERAL;
        }

        // 3. Desglose fiscal (IVA general 16% o zona fronteriza 8% sobre todo el concepto)
        $breakdown = TaxCalculator::compute($partsTotal, $laborTotal, $rate);

        // 4. Persistimos la tasa y el total de la orden
        $repairOrder->update([
            'tax_rate'       => $rate,
            'estimated_cost' => $breakdown['total'],
        ]);

        return $breakdown;
    }

    private function configuredRate(): float
    {
        $rate = (float) Setting::current()->iva;

        return TaxCalculator::isValidRate($rate) ? $rate : TaxCalculator::GENERAL;
    }
}
