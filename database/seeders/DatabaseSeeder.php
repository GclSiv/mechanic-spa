<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Datos base para un taller nuevo: catálogos, marcas y modelos, y configuración vacía.
 * No crea usuarios ni clientes de prueba. El primer usuario dueño se crea con Tinker.
 * Ejecutar: php artisan db:seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogSeeder::class,
            VehicleCatalogSeeder::class,
            VehicleSeeder::class,
            PartSeeder::class,
        ]);

        Setting::current();
    }
}
