<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase B · Clasificación del vehículo. Columnas nuevas, nullable y SIN backfill:
 * los vehículos históricos conservan null y se muestran como "Tipo no especificado".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('vehicle_type_id')->nullable()->after('model_id')
                ->constrained('vehicle_types')->nullOnDelete();
            $table->foreignId('vehicle_subtype_id')->nullable()->after('vehicle_type_id')
                ->constrained('vehicle_subtypes')->nullOnDelete();
            $table->foreignId('fuel_type_id')->nullable()->after('vehicle_subtype_id')
                ->constrained('fuel_types')->nullOnDelete();
            $table->foreignId('transmission_id')->nullable()->after('fuel_type_id')
                ->constrained('transmissions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transmission_id');
            $table->dropConstrainedForeignId('fuel_type_id');
            $table->dropConstrainedForeignId('vehicle_subtype_id');
            $table->dropConstrainedForeignId('vehicle_type_id');
        });
    }
};
