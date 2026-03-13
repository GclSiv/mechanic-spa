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
        $table->string('customer_name');
        $table->string('vehicle_details');
        $table->string('vin_serial')->nullable(); 
        $table->string('fuel_level'); // Guardará: 0, 1/4, 1/2, 3/4, 1
        $table->text('symptoms');
        $table->string('status')->default('Ingresado'); // Estado inicial del equipo
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
