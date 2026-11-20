<?php

namespace Database\Seeders;

use App\Models\ClientStatus;
use App\Models\Gender;
use App\Models\MechanicType;
use App\Models\RepairOrderStatus;
use Illuminate\Database\Seeder;

/**
 * Catálogos base del sistema. Es seguro ejecutarlo varias veces: usa firstOrCreate.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Male', 'Female'] as $name) {
            Gender::firstOrCreate(['name' => $name]);
        }

        foreach ([
            ['name' => 'Active', 'color' => 'bg-green-500'],
            ['name' => 'Debtor', 'color' => 'bg-red-500'],
        ] as $status) {
            ClientStatus::firstOrCreate(['name' => $status['name']], ['color' => $status['color']]);
        }

        foreach (['General Mechanic', 'Electromechanic'] as $name) {
            MechanicType::firstOrCreate(['name' => $name]);
        }

        $statuses = [
            ['slug' => 'recibido',      'name' => 'Received',       'color_class' => 'bg-green-500'],
            ['slug' => 'diagnostico',   'name' => 'In Diagnosis',   'color_class' => 'bg-yellow-500'],
            ['slug' => 'espera-piezas', 'name' => 'Awaiting Parts', 'color_class' => 'bg-orange-500'],
            ['slug' => 'en-reparacion', 'name' => 'In Repair',      'color_class' => 'bg-blue-500'],
            ['slug' => 'reparado',      'name' => 'Repaired',       'color_class' => 'bg-green-600'],
            ['slug' => 'entregado',     'name' => 'Delivered',      'color_class' => 'bg-gray-500'],
            ['slug' => 'rechazado',     'name' => 'Rejected',       'color_class' => 'bg-red-600'],
        ];

        foreach ($statuses as $status) {
            RepairOrderStatus::firstOrCreate(
                ['slug' => $status['slug']],
                ['name' => $status['name'], 'color_class' => $status['color_class']]
            );
        }
    }
}
