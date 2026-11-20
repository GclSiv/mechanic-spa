<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientFiscalProfile;
use App\Models\ClientStatus;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Preparación para JVL Mecánica Integral: colores (azul y rojo), logotipo y 4 clientes de prueba
 * tomados de su base. Es idempotente: se puede correr varias veces sin duplicar nada.
 * Ejecutar: php artisan db:seed --class=JvlSetupSeeder
 */
class JvlSetupSeeder extends Seeder
{
    public function run(): void
    {
        $this->configurarMarca();
        $this->clientesDePrueba();
    }

    private function configurarMarca(): void
    {
        $settings = Setting::current();

        $logoOrigen = database_path('seeders/assets/jvl-logo-negro.png');
        $logoRuta   = 'logos/jvl-logo-negro.png';

        if (File::exists($logoOrigen)) {
            Storage::disk('public')->put($logoRuta, File::get($logoOrigen));
        }

        $settings->update([
            'primary_color'   => '#1E3A8A', // Azul JVL
            'secondary_color' => '#DC2626', // Rojo JVL
            'logo_path'       => Storage::disk('public')->exists($logoRuta) ? $logoRuta : $settings->logo_path,
        ]);
    }

    private function clientesDePrueba(): void
    {
        $activo = ClientStatus::firstOrCreate(['name' => 'Active'], ['color' => 'bg-green-500'])->id;

        // Datos tomados de la base del cliente (clave anterior en comentario).
        $clientes = [
            // 00001 — persona, RFC genérico (público en general)
            ['nombre' => 'Eli', 'apellidos' => 'Cruz Jimenez', 'telefono' => '9541781375', 'correo' => 'logisticajmz@hotmail.com',
             'direccion' => null, 'rfc' => 'XAXX010101000', 'razon' => null],
            // 00003 — empresa, RFC moral
            ['nombre' => 'OPERADORA DE RECURSOS UNIVERSAL', 'apellidos' => '', 'telefono' => '9511801275', 'correo' => 'crmcu05@gmail.com',
             'direccion' => 'Independencia 401, Oaxaca Centro, Oaxaca de Juárez, Oaxaca 68000', 'rfc' => 'ORU0105078D3', 'razon' => 'OPERADORA DE RECURSOS UNIVERSAL'],
            // 00062 — persona física con RFC real
            ['nombre' => 'JOSE EDUARDO', 'apellidos' => 'JARQUIN CUENCA', 'telefono' => '9515075204', 'correo' => 'jossjarquin23@gmail.com',
             'direccion' => 'Carretera a Huayapam 3, San Andrés Huayápam, Oaxaca 68287', 'rfc' => 'JACE9504234N5', 'razon' => 'JOSE EDUARDO JARQUIN CUENCA'],
            // 00112 — mismo RFC que 00062 (caso de conflicto para probar el bloqueo)
            ['nombre' => 'Jacqueline', 'apellidos' => 'Platas', 'telefono' => '9515075204', 'correo' => 'jossjarquin23@gmail.com',
             'direccion' => null, 'rfc' => 'JACE9504234N5', 'razon' => 'Jacqueline Platas'],
        ];

        foreach ($clientes as $c) {
            $cliente = Client::firstOrCreate(
                ['first_name' => $c['nombre'], 'last_name' => $c['apellidos'], 'phone' => $c['telefono']],
                [
                    'email'            => $c['correo'],
                    'address'          => $c['direccion'],
                    'rfc'              => $c['rfc'],
                    'country'          => 'MX',
                    'client_status_id' => $activo,
                ]
            );

            if ($c['rfc'] !== 'XAXX010101000' && $c['razon']) {
                ClientFiscalProfile::firstOrCreate(
                    ['client_id' => $cliente->id, 'rfc' => $c['rfc']],
                    ['razon_social' => $c['razon']]
                );
            }
        }
    }
}
