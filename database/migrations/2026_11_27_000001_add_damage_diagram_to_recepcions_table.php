<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot del diagrama de daños usado al registrar daños. Nullable, SIN backfill: las recepciones
 * históricas se resuelven en lectura (App\Support\DiagramaDano::deRecepcion), no se reescriben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcions', function (Blueprint $table) {
            $table->string('damage_diagram', 30)->nullable()->after('damages');
        });
    }

    public function down(): void
    {
        Schema::table('recepcions', function (Blueprint $table) {
            $table->dropColumn('damage_diagram');
        });
    }
};
