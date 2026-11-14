<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            // Tasa de IVA fijada en la orden al cotizar (16.00 o 8.00)
            $table->decimal('tax_rate', 5, 2)->nullable()->after('estimated_cost');
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }
};
