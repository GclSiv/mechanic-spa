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
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('name');          // Ejemplo: Pantalla LED 14" o Filtro de Aceite
        $table->string('sku')->unique(); // Código de barras o referencia
        $table->decimal('cost_price', 10, 2); // Lo que a ti te cuesta
        $table->decimal('sale_price', 10, 2); // A cómo lo vendes
        $table->integer('stock')->default(0); // Cuántas tienes en el estante
        $table->integer('low_stock_threshold')->default(2); // Alerta cuando queden pocas
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
