<?php

namespace App\Actions\RepairOrders;

use App\Models\RepairOrder;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class CalculateOrderTotalsAction
{
    /**
     * Ejecuta el cálculo de totales ignorando cualquier input del frontend.
     * Todo se calcula estrictamente desde la base de datos.
     *
     * @param RepairOrder $repairOrder
     * @return array Desglose financiero
     */
    public function execute(RepairOrder $repairOrder): array
    {
        // 1. Sumarización estricta desde la BD
        $items = $repairOrder->items()
            ->select('type', DB::raw('SUM(subtotal) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');
            
        $partsTotal = (float) ($items['part'] ?? 0);
        $laborTotal = (float) ($items['labor'] ?? 0);

        // 2. Configuración global
        $setting = Setting::first();
        
        $taxRate = ($setting && $setting->iva !== null)
            ? ($setting->iva / 100)
            : 0.16;
            
        $taxType = $setting ? $setting->tax_type : 'MX';

        // 3. Cálculo de impuesto
        $taxAmount = 0;

        if ($setting && $taxType === 'US' && abs($setting->iva - 8.75) < 0.01) {
            // Exento
            $taxAmount = 0;
        } else {
            $taxAmount = round($laborTotal * $taxRate, 2);
        }

        // 4. Gran Total
        $grandTotal = $partsTotal + $laborTotal + $taxAmount;

        // 5. Persistencia
        $repairOrder->update([
            'estimated_cost' => round($grandTotal, 2)
        ]);

        // 6. DTO
        return [
            'parts_total' => round($partsTotal, 2),
            'labor_total' => round($laborTotal, 2),
            'tax_amount'  => round($taxAmount, 2),
            'grand_total' => round($grandTotal, 2),
            'tax_rate'    => $setting->iva ?? 16.00,
            'tax_type'    => $taxType
        ];
    }
}