<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientStatus;
use Illuminate\Database\Seeder;

/**
 * Clientes FICTICIOS para pruebas. NO está en DatabaseSeeder: ejecútalo a mano solo si
 * quieres datos de demostración. Los datos reales se deben restaurar desde tu respaldo.
 * Ejecutar: php artisan db:seed --class=ClientDemoSeeder
 */
class ClientDemoSeeder extends Seeder
{
    public function run(): void
    {
        $activo = ClientStatus::where('name', 'Active')->value('id');

        $clients = [
            ['first_name' => 'Cliente',  'last_name' => 'Demo Uno',   'phone' => '951 555 0101', 'email' => 'demo1@ejemplo.test', 'address' => 'Calle Ejemplo 1, Oaxaca', 'rfc' => 'XAXX010101000'],
            ['first_name' => 'Cliente',  'last_name' => 'Demo Dos',   'phone' => '951 555 0102', 'email' => 'demo2@ejemplo.test', 'address' => 'Calle Ejemplo 2, Oaxaca', 'rfc' => 'XAXX010101000'],
            ['first_name' => 'Cliente',  'last_name' => 'Demo Tres',  'phone' => '951 555 0103', 'email' => 'demo3@ejemplo.test', 'address' => 'Calle Ejemplo 3, Oaxaca', 'rfc' => 'XAXX010101000'],
        ];

        foreach ($clients as $c) {
            Client::firstOrCreate(
                ['email' => $c['email']],
                $c + ['country' => 'MX', 'client_status_id' => $activo]
            );
        }
    }
}
