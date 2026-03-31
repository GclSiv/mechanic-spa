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
       Schema::table('repair_order_items', function (Blueprint $table) {
    // 'part' = Refacción, 'labor' = Mano de Obra
    $table->enum('type', ['part', 'labor'])->default('part')->after('description');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repair_order_items', function (Blueprint $table) {
            //
        });
    }
};