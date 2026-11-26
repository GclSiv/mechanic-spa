<?php

namespace App\Http\Requests\Concerns;

use App\Models\VehicleSubtype;

/**
 * Reglas de clasificación del vehículo (tipo, subtipo, combustible, transmisión).
 * Un subtipo siempre debe pertenecer al tipo: se rechaza, nunca se guarda huérfano.
 */
trait ValidaClasificacionVehiculo
{
    protected function reglasClasificacion(): array
    {
        return [
            'vehicle_type_id'    => 'nullable|exists:vehicle_types,id',
            'vehicle_subtype_id' => 'nullable|exists:vehicle_subtypes,id',
            'fuel_type_id'       => 'nullable|exists:fuel_types,id',
            'transmission_id'    => 'nullable|exists:transmissions,id',
        ];
    }

    /** @param mixed $tipoId tipo efectivo del vehículo tras el cambio */
    protected function validarSubtipoCompatible($validador, mixed $tipoId): void
    {
        if (! $this->filled('vehicle_subtype_id')) {
            return;
        }

        $subtipo = VehicleSubtype::find($this->input('vehicle_subtype_id'));

        if (! $subtipo || $tipoId === null || (int) $subtipo->vehicle_type_id !== (int) $tipoId) {
            $validador->errors()->add('vehicle_subtype_id', 'El subtipo no corresponde al tipo de vehículo seleccionado.');
        }
    }
}
