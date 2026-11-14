<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Términos de garantía configurables por el administrador (se imprimen en la recepción).
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('warranty_service_enabled')->default(true);
            $table->unsignedSmallInteger('warranty_service_days')->default(30);
            $table->unsignedInteger('warranty_service_km')->nullable()->default(1000);
            $table->boolean('warranty_parts_enabled')->default(true);
            $table->unsignedSmallInteger('warranty_parts_days')->default(90);
            $table->text('warranty_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'warranty_service_enabled',
                'warranty_service_days',
                'warranty_service_km',
                'warranty_parts_enabled',
                'warranty_parts_days',
                'warranty_notes',
            ]);
        });
    }
};
