<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Estado "En reparación" para el tablero (columna entre Esp. piezas y Listo).
     * Aditivo: no modifica ni renombra los estados existentes.
     */
    public function up(): void
    {
        DB::table('repair_order_statuses')->insertOrIgnore([
            'name'        => 'In Repair',
            'slug'        => 'en-reparacion',
            'color_class' => 'bg-blue-500',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('repair_order_statuses')->where('slug', 'en-reparacion')->delete();
    }
};
