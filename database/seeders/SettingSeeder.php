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
       Setting::create([
        'company_name'   => 'JK AUTOMOTIVE CARE INC',
        'address'        => '851 W Mission Ave Escondido, CA 92025',
        'phone'          => '760-798-6723',
        'email'          => 'JKAUTOMOTIVECARE1@gmail.com',
        'license_number' => '313901',
        'clauses'        => 'You are entitled by law to the return of all parts replaced, except those for which there is a core charge.' . "\n\n" .
             'STORAGE FEES: Vehicles not picked up within 48 hours of completion are subject to a $50/day storage fee.' . "\n\n" .
             'I authorize the above repairs and employees to operate the vehicle for testing.', 
        'logo_path'      => 'images/logo-taller.svg',
            
            // Colores corporativos del manual
            'primary_color'   => '#10213E', // Azul JK
            'secondary_color' => '#EE2857', // Rojo JK
            'accent_color'    => '#68C6BA', // Teal JK
    ]);
    }
}
