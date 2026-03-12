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
        Schema::create('client_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // Ej: Activo, Inactivo, Moroso
            $table->string('description', 255)->nullable();
            
            // Este campo es clave para Tailwind (ej: 'bg-green-500', 'bg-red-500')
            //$table->string('color');
            $table->string('color', 50)->default('bg-gray-500');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_statuses');
    }
};
