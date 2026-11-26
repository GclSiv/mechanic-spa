<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Testigos del tablero (catálogo). Las recepciones siguen guardando códigos en recepcions.witnesses;
 * no hay tabla pivote ni backfill. Los datos iniciales los crea WarningLightSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warning_lights', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warning_lights');
    }
};
