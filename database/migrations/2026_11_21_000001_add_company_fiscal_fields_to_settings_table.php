<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos comerciales y fiscales de la empresa que faltaban en settings (EMPRESA-1.1).
     * Todos son nullable: la empresa se configura después, sin valores inventados.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('whatsapp', 30)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('regimen_fiscal', 150)->nullable();
            $table->string('codigo_postal_fiscal', 5)->nullable();
            $table->string('domicilio_fiscal', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp',
                'website',
                'regimen_fiscal',
                'codigo_postal_fiscal',
                'domicilio_fiscal',
            ]);
        });
    }
};
