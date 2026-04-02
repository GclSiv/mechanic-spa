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
        // El botón rojo: Eliminamos la tabla redundante
        Schema::dropIfExists('configurations');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // El salvavidas: Recreamos la estructura original por si hacemos rollback
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();
            $table->decimal('iva', 5, 2)->default(16.00);
            $table->string('razon_social', 500)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('phone', 200)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('rfc', 200)->nullable();
            $table->timestamps();
        });
    }
};