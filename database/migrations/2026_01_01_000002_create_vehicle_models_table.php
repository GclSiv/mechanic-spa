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
        Schema::create('vehicle_models', function (Blueprint $table) {
        $table->id();
        // Esta es la llave foránea que conecta con la tabla brands 🔗
        $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
        $table->string('name'); // El nombre del modelo (ej. Sentra, Civic)
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_models');
    }
};
