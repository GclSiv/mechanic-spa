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

        \App\Models\Gender::create(['name' => 'Male']);
        \App\Models\Gender::create(['name' => 'Female']);

        \App\Models\ClientStatus::create(['name' => 'Active', 'color' => 'bg-green-500']);
        \App\Models\ClientStatus::create(['name' => 'Debtor', 'color' => 'bg-red-500']);

        \App\Models\MechanicType::create(['name' => 'General Mechanic']);
        \App\Models\MechanicType::create(['name' => 'Electromechanic']);

        \App\Models\RepairOrderStatus::create(['name' => 'Received', 'slug' => 'recibido', 'color_class' => 'bg-gray-500']);
        \App\Models\RepairOrderStatus::create(['name' => 'In Diagnosis', 'slug' => 'diagnostico', 'color_class' => 'bg-yellow-500']);
        \App\Models\RepairOrderStatus::create(['name' => 'Awaiting Parts', 'slug' => 'espera-piezas', 'color_class' => 'bg-orange-500']);
        \App\Models\RepairOrderStatus::create(['name' => 'Repaired', 'slug' => 'reparado', 'color_class' => 'bg-green-500']);
        \App\Models\RepairOrderStatus::create(['name' => 'Delivered', 'slug' => 'entregado', 'color_class' => 'bg-blue-600']);
        \App\Models\RepairOrderStatus::create(['name' => 'Rejected', 'slug' => 'rechazado', 'color_class' => 'bg-red-600']);

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
            'name' => 'Synthetic Oil 5W-30 (Full Synthetic)',
            'sku' => 'OIL-5W30-FS',
            'cost_price' => 120.00,
            'sale_price' => 210.00,
            'stock' => 24
        ]);

        \App\Models\Part::create([
            'name' => 'Premium Oil Filter',
            'sku' => 'FIL-OIL-001',
            'cost_price' => 85.00,
            'sale_price' => 150.00,
            'stock' => 15
        ]);
    }
}