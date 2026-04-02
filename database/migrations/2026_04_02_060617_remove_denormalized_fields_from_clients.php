<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'fuel_level',
                'technical_symptoms',
                'miles',
                'vin',
                'engine'
            ]);
        });
    }

    public function down(): void
    {
        // Fallback en caso de Rollback
        Schema::table('clients', function (Blueprint $table) {
            $table->string('fuel_level')->nullable();
            $table->text('technical_symptoms')->nullable();
            $table->integer('miles')->nullable();
            $table->string('vin')->nullable();
            $table->string('engine')->nullable();
        });
    }
};