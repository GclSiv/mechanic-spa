<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcions', function (Blueprint $table) {
            // 1. PRIMERO: Destruir el candado (Llave Foránea)
            $table->dropForeign(['brand_id']);

            // 2. SEGUNDO: Ahora sí, borrar todas las columnas
            $table->dropColumn([
                'first_name', 'phone', 'address', 'rfc', 
                'brand_id', 'vehicle_model_id', 'year', 'plate', 'vin_serial' 
            ]);
        });
    }

    public function down(): void
    {
        // Recreamos los campos en caso de rollback (emergencia)
        Schema::table('recepcions', function (Blueprint $table) {
            $table->string('first_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('rfc')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('vehicle_model_id')->nullable();
            $table->string('year')->nullable();
            $table->string('plate')->nullable();
            $table->string('vin_serial')->nullable();
        });
    }
};