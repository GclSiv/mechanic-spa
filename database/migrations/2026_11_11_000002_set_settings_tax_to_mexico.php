<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Las configuraciones con la regla de USA (8.75%) pasan a México (16%).
     * Es un cambio de datos y no se revierte.
     */
    public function up(): void
    {
        DB::table('settings')
            ->where('iva', 8.75)
            ->update(['iva' => 16.00, 'tax_type' => 'MX']);
    }

    public function down(): void
    {
        //
    }
};
