<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * ✅ FIX #10: La migración original de vehicles usaba brand/model como strings.
 * El modelo Vehicle usa brand_id y model_id como FK. Esta migración los sincroniza.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Solo agregar si no existen ya (idempotente)
            if (!Schema::hasColumn('vehicles', 'brand_id')) {
                $table->foreignId('brand_id')->nullable()->after('client_id')->constrained('brands');
            }
            if (!Schema::hasColumn('vehicles', 'model_id')) {
                $table->foreignId('model_id')->nullable()->after('brand_id')->constrained('vehicle_models');
            }
            // Los campos string legacy brand/model quedan como nullable para no romper datos existentes
            if (Schema::hasColumn('vehicles', 'brand')) {
                $table->string('brand')->nullable()->change();
            }
            if (Schema::hasColumn('vehicles', 'model')) {
                $table->string('model')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['model_id']);
            $table->dropColumn(['brand_id', 'model_id']);
        });
    }
};