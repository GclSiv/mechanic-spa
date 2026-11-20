<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite asignar un pago (anticipo) a una cotización específica.
     * Los pagos existentes quedan con quote_id NULL (anticipo general de la orden).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('quote_id')->nullable()->after('repair_order_id')
                ->constrained('quotes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quote_id');
        });
    }
};
