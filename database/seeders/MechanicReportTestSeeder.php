<?php

namespace Database\Seeders;

use App\Models\Mechanic;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use Illuminate\Database\Seeder;

/**
 * Datos de PRUEBA para validar el reporte de productividad de mecánicos.
 * Crea 2 mecánicos y 6 órdenes terminadas en el mes actual.
 * Ejecutar: php artisan db:seed --class=MechanicReportTestSeeder
 * Borrar después: ver instrucciones en el chat (folios que empiezan con PRUEBA-).
 */
class MechanicReportTestSeeder extends Seeder
{
    public function run(): void
    {
        $statusIds = RepairOrderStatus::whereIn('slug', ['reparado', 'entregado'])->pluck('id')->values();

        if ($statusIds->isEmpty()) {
            $this->command?->error('No existen los estados "reparado" y "entregado". Corre primero el DatabaseSeeder.');
            return;
        }

        $mecanicos = [
            ['name' => 'PRUEBA Mecánico Uno', 'rate' => 10],
            ['name' => 'PRUEBA Mecánico Dos', 'rate' => 15],
        ];

        $genderId = \App\Models\Gender::value('id');
        $tipoId   = \App\Models\MechanicType::value('id');

        if (! $genderId || ! $tipoId) {
            $this->command?->error('Faltan géneros o tipos de mecánico. Corre primero el DatabaseSeeder.');
            return;
        }

        $contador = 0;

        foreach ($mecanicos as $datos) {
            $mecanico = Mechanic::factory()->create([
                'name'             => $datos['name'],
                'commission_rate'  => $datos['rate'],
                'gender_id'        => $genderId,
                'mechanic_type_id' => $tipoId,
            ]);

            for ($i = 1; $i <= 3; $i++) {
                $contador++;
                $orden = RepairOrder::factory()->create([
                    'folio'           => 'PRUEBA-' . str_pad((string) $contador, 3, '0', STR_PAD_LEFT),
                    'mechanic_id'     => $mecanico->id,
                    'status_id'       => $statusIds->random(),
                    'entry_date'      => now()->subDays(10 + $i)->toDateString(),
                    'delivery_date'   => now()->subDays($i)->toDateString(),
                ]);

                // Forzamos la fecha de actualización dentro del mes actual
                RepairOrder::whereKey($orden->id)->update([
                    'updated_at' => now()->subDays($i),
                ]);

                RepairOrderItem::factory()->create([
                    'repair_order_id' => $orden->id,
                    'type'            => 'labor',
                    'quantity'        => 1,
                    'unit_price'      => 800,
                    'subtotal'        => 800,
                ]);

                RepairOrderItem::factory()->create([
                    'repair_order_id' => $orden->id,
                    'type'            => 'part',
                    'quantity'        => 2,
                    'unit_price'      => 150,
                    'subtotal'        => 300,
                ]);
            }
        }

        $this->command?->info("Listo: 2 mecánicos de prueba y {$contador} órdenes (folios PRUEBA-001 a PRUEBA-{$contador}).");
    }
}
