<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       Setting::current()->update([
        'company_name'   => 'Taller Demo',
        'address'        => 'Dirección de prueba',
        'phone'          => '5550000000',
        'email'          => 'demo@example.com',
        'license_number' => '',
        'clauses'        => 'Cláusulas de ejemplo. Configúralas en /configuracion.',
        'logo_path'      => null,
            
            'primary_color'   => '#1F2937',
            'secondary_color' => '#2563EB',
            'accent_color'   => '#64748B',
    ]);
    }
}
