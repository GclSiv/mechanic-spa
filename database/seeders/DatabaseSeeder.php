<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // --- 1. CATÁLOGOS BASE (Sin dependencias) ---
        $this->call([
            SettingSeeder::class,      // ✅ Aquí ya se configura JK Automotive y el IVA
        ]);

        \App\Models\Gender::create(['name' => 'Masculino']);
        \App\Models\Gender::create(['name' => 'Femenino']);

        \App\Models\ClientStatus::create(['name' => 'Activo', 'color' => 'bg-green-500']);
        \App\Models\ClientStatus::create(['name' => 'Deudor', 'color' => 'bg-red-500']);

        \App\Models\MechanicType::create(['name' => 'Mecánico General']);
        \App\Models\MechanicType::create(['name' => 'Electromecánico']);

        \App\Models\RepairOrderStatus::create(['name' => 'Recibido', 'slug' => 'recibido', 'color_class' => 'bg-gray-500']);
        \App\Models\RepairOrderStatus::create(['name' => 'En Diagnóstico', 'slug' => 'diagnostico', 'color_class' => 'bg-yellow-500']);
        \App\Models\RepairOrderStatus::create(['name' => 'Esperando Piezas', 'slug' => 'espera-piezas', 'color_class' => 'bg-orange-500']);
        \App\Models\RepairOrderStatus::create(['name' => 'Reparado', 'slug' => 'reparado', 'color_class' => 'bg-green-500']);
        \App\Models\RepairOrderStatus::create(['name' => 'Entregado', 'slug' => 'entregado', 'color_class' => 'bg-blue-600']);

        // --- 2. USUARIOS Y PERSONAL ---
        \App\Models\User::factory()->create([
            'name' => 'Admin JK Automotive',
            'email' => 'admin@jkautomotive.com',
            'password' => bcrypt('password'), // 🔑 Recuerda cambiarla en producción
        ]);

        \App\Models\Mechanic::create([
            'name' => 'Juan Pérez',
            'gender_id' => 1,
            'mechanic_type_id' => 1,
            'phone' => '7607986723'
        ]);

        // --- 3. DATOS DE PRUEBA TRANSACCIONALES ---
        $this->call([
            VehicleSeeder::class, 
            ClientSeeder::class,
            ClientPhotoSeeder::class,
        ]);

        // --- 4. INVENTARIO DE PIEZAS ---
        \App\Models\Part::create([
            'name' => 'Aceite Sintético 5W-30 (Full Synthetic)',
            'sku' => 'OIL-5W30-FS',
            'cost_price' => 120.00,
            'sale_price' => 210.00,
            'stock' => 24
        ]);

        \App\Models\Part::create([
            'name' => 'Filtro de Aceite Premium',
            'sku' => 'FIL-OIL-001',
            'cost_price' => 85.00,
            'sale_price' => 150.00,
            'stock' => 15
        ]);
    }
}