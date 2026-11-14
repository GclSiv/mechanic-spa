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

        \App\Models\Gender::firstOrCreate(['name' => 'Male']);
        \App\Models\Gender::firstOrCreate(['name' => 'Female']);

        \App\Models\ClientStatus::firstOrCreate(['name' => 'Active'], ['color' => 'bg-green-500']);
        \App\Models\ClientStatus::firstOrCreate(['name' => 'Debtor'], ['color' => 'bg-red-500']);

        \App\Models\MechanicType::firstOrCreate(['name' => 'General Mechanic']);
        \App\Models\MechanicType::firstOrCreate(['name' => 'Electromechanic']);

        \App\Models\RepairOrderStatus::firstOrCreate(['slug' => 'recibido'], ['name' => 'Received', 'color_class' => 'bg-gray-500']);
        \App\Models\RepairOrderStatus::firstOrCreate(['slug' => 'diagnostico'], ['name' => 'In Diagnosis', 'color_class' => 'bg-yellow-500']);
        \App\Models\RepairOrderStatus::firstOrCreate(['slug' => 'espera-piezas'], ['name' => 'Awaiting Parts', 'color_class' => 'bg-orange-500']);
        \App\Models\RepairOrderStatus::firstOrCreate(['slug' => 'reparado'], ['name' => 'Repaired', 'color_class' => 'bg-green-500']);
        \App\Models\RepairOrderStatus::firstOrCreate(['slug' => 'entregado'], ['name' => 'Delivered', 'color_class' => 'bg-blue-600']);
        \App\Models\RepairOrderStatus::firstOrCreate(['slug' => 'rechazado'], ['name' => 'Rejected', 'color_class' => 'bg-red-600']);
        \App\Models\RepairOrderStatus::firstOrCreate(['slug' => 'en-reparacion'], ['name' => 'In Repair', 'color_class' => 'bg-blue-500']);

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