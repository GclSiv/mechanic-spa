<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Porcentaje de comisión del mecánico sobre la mano de obra (sin IVA).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('mechanics', 'commission_rate')) {
            Schema::table('mechanics', function (Blueprint $table) {
                $table->decimal('commission_rate', 5, 2)->default(0)->after('phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mechanics', 'commission_rate')) {
            Schema::table('mechanics', function (Blueprint $table) {
                $table->dropColumn('commission_rate');
            });
        }
    }
};
