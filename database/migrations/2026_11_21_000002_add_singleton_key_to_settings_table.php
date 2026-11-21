<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Garantiza en la base de datos que exista como máximo UNA configuración empresarial:
     * la columna técnica singleton_key vale siempre 'default' y es UNIQUE.
     *
     * No borra ni modifica filas existentes. Si ya hay más de una fila, la migración se detiene
     * antes de tocar la tabla, porque elegir cuál conservar es una decisión humana.
     */
    public function up(): void
    {
        $filas = DB::table('settings')->count();

        if ($filas > 1) {
            throw new \RuntimeException(
                "La tabla settings tiene {$filas} filas y la restricción singleton permite como máximo una. "
                . 'No se modificó nada. Revisa qué configuración conservar, elimina manualmente las demás y vuelve a migrar.'
            );
        }

        Schema::table('settings', function (Blueprint $table) {
            // El DEFAULT rellena la fila existente (si la hay) con 'default'.
            $table->string('singleton_key', 20)->default('default')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['singleton_key']);
            $table->dropColumn('singleton_key');
        });
    }
};
