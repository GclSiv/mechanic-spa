<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('repair_order_items', 'type')) {
                // 'part' = refacción, 'labor' = mano de obra
                $table->enum('type', ['part', 'labor'])
                      ->default('part')
                      ->after('subtotal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('repair_order_items', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
