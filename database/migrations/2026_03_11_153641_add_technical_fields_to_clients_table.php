<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
        // Agregamos solo los campos que NO existen en tu captura de DB anterior
        // Usamos 'after' para mantener el orden visual en la base de datos
        
        $table->string('fuel_level')->nullable()->after('vin');
        $table->text('technical_symptoms')->nullable()->after('fuel_level');
        $table->text('client_observations')->nullable()->after('technical_symptoms');
        $table->string('status')->default('Ingresado')->after('client_observations');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            //
        });
    }
};
