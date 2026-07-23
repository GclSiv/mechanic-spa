<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Categorías base para el taller automotriz
        $now = now();
        \Illuminate\Support\Facades\DB::table('categories')->insert([
            ['name' => 'Aceites y Lubricantes', 'slug' => 'aceites',      'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Frenos',                'slug' => 'frenos',       'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Motor',                 'slug' => 'motor',        'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Suspensión',            'slug' => 'suspension',   'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Eléctrico',             'slug' => 'electrico',    'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Filtros',               'slug' => 'filtros',      'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Carrocería',            'slug' => 'carroceria',   'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Transmisión',           'slug' => 'transmision',  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Llantas y Neumáticos',  'slug' => 'llantas',      'created_at' => $now, 'updated_at' => $now],
            ['name' => 'General',               'slug' => 'general',      'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
