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
        Schema::create('repair_orders', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique(); // Ejemplo: OR-2026-001
        
        // Unimos todas tus piezas:
        $table->foreignId('client_id')->constrained();         // Punto 1
        $table->foreignId('vehicle_id')->constrained();        // Punto 18
        $table->foreignId('mechanic_id')->constrained();       // Punto 9
        $table->foreignId('status_id')->constrained('repair_order_statuses'); // Punto 5
        
        $table->text('problem_description'); // Lo que reporta el cliente
        $table->decimal('estimated_cost', 10, 2)->default(0);
        $table->date('entry_date');
        $table->date('delivery_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repair_orders');
    }
};
