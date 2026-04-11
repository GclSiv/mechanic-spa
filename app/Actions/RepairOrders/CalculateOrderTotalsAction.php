<?php

namespace App\Actions\RepairOrders;

use App\Models\RepairOrder;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class CalculateOrderTotalsAction
{
    public function execute(RepairOrder $repairOrder): array
    {
        // 1. Obtener la suma separada directamente de la base de datos (Refacciones vs Mano de Obra)
        $items = $repairOrder->items()
            ->select('type', DB::raw('SUM(subtotal) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        // Extraemos los valores garantizando que sean números (si no hay, es 0)
        $partsTotal = (float) ($items['part'] ?? 0);
        $laborTotal = (float) ($items['labor'] ?? 0);
        $subtotalGeneral = $partsTotal + $laborTotal;

        // 2. Obtener la configuración fiscal actual de la empresa
        $setting = Setting::first();
        $taxRate = $setting && $setting->iva !== null ? (float) $setting->iva : 16.00;

        $taxAmount = 0;

        // 3. LA LÓGICA FISCAL INTELIGENTE
        if ($taxRate == 8.75) {
            // Regla USA: El Tax (8.75%) SOLO aplica a las refacciones (Piezas)
            $taxAmount = $partsTotal * ($taxRate / 100);
        } else {
            // Regla MÉXICO: El IVA (16% u otros) aplica parejo a TODO (Piezas + Mano de obra)
            $taxAmount = $subtotalGeneral * ($taxRate / 100);
        }

        // Calculamos el Gran Total
        $total = $subtotalGeneral + $taxAmount;

        // 4. Actualizamos el costo en la tabla principal de la orden silenciosamente
        $repairOrder->update(['estimated_cost' => $total]);

        // 5. Retornamos el desglose financiero completo para Vue y el PDF
        return [
            'parts_subtotal' => round($partsTotal, 2),
            'labor_subtotal' => round($laborTotal, 2),
            'subtotal'       => round($subtotalGeneral, 2),
            'tax_rate'       => $taxRate,
            'tax'            => round($taxAmount, 2),
            'total'          => round($total, 2),
        ];
    }
}