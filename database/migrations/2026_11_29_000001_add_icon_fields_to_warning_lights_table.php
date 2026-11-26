<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Incremental sobre warning_lights (no edita la migración de creación, por si ya se ejecutó).
 *
 * - icon: clave de un icono del registro interno (App\Support\IconosTestigo). Nunca una ruta ni un SVG.
 * - custom_icon_path: ruta INTERNA (relativa al disco privado de Laravel) de un icono personalizado ya
 *   validado y normalizado. Nunca una URL externa ni una ruta que escriba el usuario.
 *
 * Idempotente: si la columna ya existe (p. ej. creada a mano), no falla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warning_lights', function (Blueprint $table) {
            if (! Schema::hasColumn('warning_lights', 'icon')) {
                $table->string('icon', 50)->default('warning')->after('name');
            }
            if (! Schema::hasColumn('warning_lights', 'custom_icon_path')) {
                $table->string('custom_icon_path', 255)->nullable()->after('icon');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warning_lights', function (Blueprint $table) {
            if (Schema::hasColumn('warning_lights', 'custom_icon_path')) {
                $table->dropColumn('custom_icon_path');
            }
            if (Schema::hasColumn('warning_lights', 'icon')) {
                $table->dropColumn('icon');
            }
        });
    }
};
