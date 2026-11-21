<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo compartido de ubicaciones (almacén, bahías, etc.).
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('locations')->insert(array_map(fn (string $nombre) => [
            'name' => $nombre, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], ['Almacén', 'Recepción', 'Bahía 1', 'Bahía 2', 'Oficina', 'Vehículo de servicio']));
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
