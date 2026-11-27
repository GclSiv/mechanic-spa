<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solicitud de cita del portal del cliente. Nunca reserva automáticamente:
     * nace como "pendiente" y el taller decide. Separada de la orden.
     */
    public function up(): void
    {
        Schema::create('appointment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('repair_order_id')->nullable()->constrained('repair_orders')->nullOnDelete();
            $table->date('preferred_date');
            $table->string('preferred_time', 10)->nullable();
            $table->string('reason', 30);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pendiente')->index();
            $table->date('confirmed_date')->nullable();
            $table->string('confirmed_time', 5)->nullable();
            $table->text('customer_note')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_requests');
    }
};
