<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Próximo servicio recomendado por vehículo. Solo se guarda y se muestra;
     * no hay envío automático de notificaciones en esta fase.
     */
    public function up(): void
    {
        Schema::create('service_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('repair_order_id')->nullable()->constrained('repair_orders')->nullOnDelete();
            $table->string('title', 120);
            $table->date('recommended_date')->nullable();
            $table->unsignedInteger('recommended_mileage')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pendiente')->index();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_reminders');
    }
};
