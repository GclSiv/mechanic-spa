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
        Schema::create('recepcions', function (Blueprint $table) {
            $table->id();
            
            // Relaciones Normalizadas (Datos fijos extraídos de clients y vehicles)
            $table->foreignId('client_id')->constrained()->cascadeOnDelete(); 
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            
            // Estado del Vehículo (Datos transaccionales de esta visita específica)
            $table->string('fuel_level');
            $table->string('miles')->nullable();
            $table->json('witnesses')->nullable(); 
            $table->json('inventory')->nullable(); 
            $table->json('photos')->nullable(); // Recuperamos el campo de fotos
            $table->text('symptoms')->nullable(); 

            // Control
            $table->string('status')->default('Pendiente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recepcions');
    }
};