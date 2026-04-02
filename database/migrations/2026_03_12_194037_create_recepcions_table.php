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
        $table->string('phone')->nullable(); // Agregado aquí de forma definitiva
        $table->foreignId('brand_id')->constrained(); 
        $table->string('vehicle_model_id'); // Guardaremos el nombre del modelo
        $table->string('fuel_level');
        $table->json('witnesses')->nullable(); // Para los iconos del tablero
        $table->json('inventory')->nullable(); // Para los accesorios
        $table->string('miles')->nullable();
        $table->string('vin_serial')->nullable();
        $table->text('symptoms')->nullable(); // Falla reportada
        $table->string('status')->default('Pendiente');
        if (!Schema::hasColumn('recepcions', 'address')) {
    $table->string('address')->nullable()->after('phone');
}
        $table->string('rfc', 20)->nullable()->after('address');
        $table->string('year', 4)->nullable()->after('vehicle_model_id');
        $table->string('plate', 20)->nullable()->after('year');
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