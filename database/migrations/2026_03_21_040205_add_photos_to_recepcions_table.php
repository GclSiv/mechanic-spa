<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcions', function (Blueprint $table) {
            // Columna tipo JSON para guardar la lista de fotos del ticket
            $table->json('photos')->nullable()->after('inventory');
        });
    }

    public function down(): void
    {
        Schema::table('recepcions', function (Blueprint $table) {
            $table->dropColumn('photos');
        });
    }
};