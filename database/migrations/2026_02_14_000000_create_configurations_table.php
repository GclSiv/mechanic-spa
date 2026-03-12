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
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();

            // Usamos decimal para dinero o porcentajes exactos (mejor que float)
            $table->decimal('iva', 5, 2)->default(16.00)->nullable();
            
            // Cambiamos varchar por string (que es el estándar de Laravel)
            $table->string('razon_social', 500)->nullable(); 
            $table->string('address', 500)->nullable();
            $table->string('phone', 200)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('rfc', 200)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configurations');
    }
};
