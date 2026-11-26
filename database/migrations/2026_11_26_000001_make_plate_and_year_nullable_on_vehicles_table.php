<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase A · Integridad de vehículos.
 *
 * - plate: nullable. El índice UNIQUE se conserva: varios NULL pueden coexistir (MySQL y SQLite
 *   permiten múltiples NULL en un índice único), pero dos placas iguales no.
 * - year: nullable. No se inventa ningún año para filas existentes; maquinaria puede registrarse sin año.
 *
 * No hay backfill: las filas existentes conservan su placa y su año tal como están.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('plate')->nullable()->change();
            $table->year('year')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Revertir exige que no existan filas con placa o año nulos; de lo contrario la base lo rechaza.
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('plate')->nullable(false)->change();
            $table->year('year')->nullable(false)->change();
        });
    }
};
