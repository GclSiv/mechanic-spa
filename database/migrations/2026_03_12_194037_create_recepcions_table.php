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
    $table->string('first_name'); 
    $table->string('phone')->nullable();
    
    // Agregamos address aquí, justo después de phone, sin el ->after()
    $table->string('address')->nullable(); 
    $table->string('rfc', 20)->nullable();
    
    $table->foreignId('brand_id')->constrained(); 
    $table->string('vehicle_model_id');
    
    // El año y la placa también van sin ->after()
    $table->string('year', 4)->nullable();
    $table->string('plate', 20)->nullable();
    
    $table->string('fuel_level');
    $table->json('witnesses')->nullable();
    $table->json('inventory')->nullable();
    $table->string('miles')->nullable();
    $table->string('vin_serial')->nullable();
    $table->text('symptoms')->nullable();
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