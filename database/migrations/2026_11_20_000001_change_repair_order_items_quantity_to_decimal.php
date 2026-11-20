<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La mano de obra se mide en magnitudes fraccionarias (p. ej. 1.50 horas).
 * La columna era INTEGER y redondeaba la cantidad guardada (1.5 quedaba en 2).
 *
 * Conversión: MODIFY quantity DECIMAL(10,2). Los enteros existentes pasan a 1.00, 2.00, etc.
 * No se corrigen registros históricos de mano de obra que ya hubieran sido redondeados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_items', function (Blueprint $table) {
            $table->decimal('quantity', 10, 2)->default(1)->change();
        });
    }

    public function down(): void
    {
        // Advertencia: al volver a entero, las cantidades fraccionarias se redondean (1.50 pasa a 2).
        Schema::table('repair_order_items', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->change();
        });
    }
};
