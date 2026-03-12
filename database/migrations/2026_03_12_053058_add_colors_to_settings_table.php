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
        Schema::table('settings', function (Blueprint $table) {
          // Solo agrega la columna si NO existe
        if (!Schema::hasColumn('settings', 'primary_color')) {
            $table->string('primary_color')->default('#10213E');
        }
        
        if (!Schema::hasColumn('settings', 'secondary_color')) {
            $table->string('secondary_color')->default('#EE2857');
        }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Las eliminamos en caso de hacer rollback
        if (Schema::hasColumn('settings', 'primary_color')) {
            $table->dropColumn('primary_color');
        }
        if (Schema::hasColumn('settings', 'secondary_color')) {
            $table->dropColumn('secondary_color');
        }
        });
    }
};
