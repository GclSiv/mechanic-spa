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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            // Relación con el Cliente (Punto 1)
        $table->foreignId('client_id')->constrained()->onDelete('cascade');

        // Datos del coche
        $table->string('plate')->unique(); // Placas (únicas para no repetir)
        $table->string('brand');           // Marca (Toyota, Ford, etc.)
        $table->string('model');           // Modelo (Hilux, Mustang)
        $table->year('year');              // Año
        $table->string('color')->nullable();
        $table->text('notes')->nullable(); // Algún golpe o detalle previo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
