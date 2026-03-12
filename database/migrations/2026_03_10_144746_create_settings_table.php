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
        Schema::create('settings', function (Blueprint $table) {
          $table->id();
        $table->string('company_name');
        $table->string('address')->nullable();
        $table->string('phone')->nullable();
        $table->string('email')->nullable();
        
        // --- CAMPOS NUEVOS QUE FALTAN ---
        $table->string('license_number')->nullable(); // Para el No. de licencia 313901
        $table->text('clauses')->nullable();         // Para los términos legales de la hoja
        $table->string('logo_path')->nullable();      // Para la ruta del logo SVG
        
        // Colores corporativos
        $table->string('primary_color')->default('#10213E');
        $table->string('secondary_color')->default('#EE2857');
        $table->string('accent_color')->default('#68C6BA');
        
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
