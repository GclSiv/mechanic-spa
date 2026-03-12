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
        Schema::create('client_photos', function (Blueprint $table) {
           $table->id();
        // El "cable" que conecta la foto con el cliente
        $table->foreignId('client_id')->constrained()->onDelete('cascade');
        // Donde guardaremos el nombre del archivo
        $table->string('path');
        $table->string('label')->nullable(); // Ej: "Falla en motor", "Golpe en puerta"
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_photos');
    }
};
