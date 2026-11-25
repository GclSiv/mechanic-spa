<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modelo de comisión por concepto de mano de obra (UI-10 · Fase 2).
     * - commission_model: 'legacy' (histórico, usa commission_rate del mecánico de la orden) | 'fixed' (nuevo).
     * - Sin backfill: los conceptos existentes quedan como 'legacy' por el default.
     * - mechanic_id es nullable solo por compatibilidad histórica; la aplicación exige mecánico en mano de obra nueva.
     * - commission_amount_snapshot es el monto fijo copiado del servicio al crear el concepto (inmutable).
     */
    public function up(): void
    {
        Schema::table('repair_order_items', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('part_id')
                ->constrained('services')->restrictOnDelete();
            $table->foreignId('mechanic_id')->nullable()->after('service_id')
                ->constrained('mechanics')->restrictOnDelete();
            $table->string('commission_model', 12)->default('legacy')->after('mechanic_id');
            $table->decimal('commission_amount_snapshot', 10, 2)->nullable()->after('commission_model');

            // Reportes de productividad: conceptos por mecánico y modelo de comisión.
            $table->index(['mechanic_id', 'commission_model'], 'roi_mechanic_model_idx');
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_items', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropForeign(['mechanic_id']);
            $table->dropIndex('roi_mechanic_model_idx');
            $table->dropColumn(['service_id', 'mechanic_id', 'commission_model', 'commission_amount_snapshot']);
        });
    }
};
