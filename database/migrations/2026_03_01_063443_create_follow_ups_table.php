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
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            // Nota: Aún no creamos la tabla repair_orders, así que solo definimos la columna
        $table->foreignId('repair_order_id'); 
        $table->foreignId('mechanic_id')->constrained(); // Punto 9
        $table->text('description'); // Lo que se hizo
        $table->dateTime('date');    // Cuándo se hizo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
