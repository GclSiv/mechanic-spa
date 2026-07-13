<?php

namespace App\Actions\RepairOrders;

use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;

class CreateRepairOrderAction
{
    /**
     * Busca o crea la RepairOrder vinculada a una Recepcion.
     * Si ya existe, la devuelve sin duplicar.
     */
    public function execute(Recepcion $recepcion): RepairOrder
    {
        // Devuelve la orden existente sin crear duplicado
        $existing = RepairOrder::where('recepcion_id', $recepcion->id)->first();
        if ($existing) {
            return $existing;
        }

        // Obtener el primer estatus por slug 'recibido'
        $status = RepairOrderStatus::where('slug', 'recibido')->first()
            ?? RepairOrderStatus::orderBy('id')->first();

        return RepairOrder::create([
            'recepcion_id'        => $recepcion->id,
            'folio'               => 'Q-' . str_pad($recepcion->id, 4, '0', STR_PAD_LEFT),
            'client_id'           => $recepcion->client_id,
            'vehicle_id'          => $recepcion->vehicle_id,
            'mechanic_id'         => null,
            'status_id'           => $status?->id,
            'problem_description' => $recepcion->symptoms ?? '',
            'entry_date'          => now(),
        ]);
    }
}
