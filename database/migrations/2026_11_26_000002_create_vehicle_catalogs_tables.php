<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase B · Catálogos de clasificación de vehículos. Los datos base se siembran en VehicleCatalogSeeder.
 * damage_diagram queda registrado para Fase E; todavía no genera ningún diagrama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('damage_diagram', 30)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicle_subtypes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_type_id')->constrained('vehicle_types')->cascadeOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('fuel_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('transmissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transmissions');
        Schema::dropIfExists('fuel_types');
        Schema::dropIfExists('vehicle_subtypes');
        Schema::dropIfExists('vehicle_types');
    }
};
