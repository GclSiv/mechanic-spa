<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separa las categorías de refacciones (part) de las de herramientas (tool).
     * Las categorías existentes quedan como part por defecto.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('type', 20)->default('part')->after('slug');
        });

        $now = now();
        DB::table('categories')->insert([
            ['name' => 'Diagnóstico', 'slug' => 'diagnostico', 'type' => 'tool', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Mecánica',    'slug' => 'mecanica',    'type' => 'tool', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Eléctrica',   'slug' => 'electrica',   'type' => 'tool', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Medición',    'slug' => 'medicion',    'type' => 'tool', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Neumática',   'slug' => 'neumatica',   'type' => 'tool', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('categories')->where('type', 'tool')->delete();

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
