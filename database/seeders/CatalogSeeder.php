<?php

namespace Database\Seeders;

use App\Models\ClientStatus;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\RepairOrderStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogos base del sistema. Es seguro ejecutarlo varias veces: usa firstOrCreate y nunca duplica.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->reconciliarGeneros();

        foreach (['Masculino', 'Femenino', 'No especificar'] as $name) {
            Gender::firstOrCreate(['name' => $name]);
        }

        foreach ([
            ['name' => 'Active', 'color' => 'bg-green-500'],
            ['name' => 'Debtor', 'color' => 'bg-red-500'],
        ] as $status) {
            ClientStatus::firstOrCreate(['name' => $status['name']], ['color' => $status['color']]);
        }

        // Especialidades (catálogo mechanic_types). Las existentes se conservan tal cual.
        foreach (['General Mechanic', 'Electromechanic', 'Motocicletas', 'Mecánico Gasolina'] as $name) {
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

    /**
     * Reconcilia géneros antiguos en inglés con los nombres en español, sin perder referencias.
     * - Solo existe el antiguo: se renombra y conserva su ID.
     * - Coexisten antiguo y nuevo: el ID histórico (antiguo) es el canónico. Los mecánicos del
     *   duplicado se reasignan al antiguo y el duplicado se elimina. Nunca se inventa un ID nuevo.
     * Idempotente: si no hay nombres antiguos, no hace nada.
     */
    private function reconciliarGeneros(): void
    {
        foreach (['Male' => 'Masculino', 'Female' => 'Femenino'] as $antiguo => $nuevo) {
            DB::transaction(function () use ($antiguo, $nuevo) {
                $viejo = Gender::where('name', $antiguo)->orderBy('id')->lockForUpdate()->first();

                if (! $viejo) {
                    return;
                }

                $duplicado = Gender::where('name', $nuevo)
                    ->where('id', '!=', $viejo->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($duplicado) {
                    Mechanic::where('gender_id', $duplicado->id)->update(['gender_id' => $viejo->id]);
                    $duplicado->delete();
                }

                $viejo->update(['name' => $nuevo]);
            });
        }
    }
}
