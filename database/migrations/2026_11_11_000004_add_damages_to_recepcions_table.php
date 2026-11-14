<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diagrama de daños previos: lista de marcas { x, y, note } en porcentaje (0-100).
     */
    public function up(): void
    {
        Schema::table('recepcions', function (Blueprint $table) {
            $table->json('damages')->nullable()->after('inventory');
        });
    }

    public function down(): void
    {
        Schema::table('recepcions', function (Blueprint $table) {
            $table->dropColumn('damages');
        });
    }
};
