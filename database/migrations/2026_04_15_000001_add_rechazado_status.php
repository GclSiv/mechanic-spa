<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('repair_order_statuses')->insertOrIgnore([
            'name'        => 'Rechazado',
            'slug'        => 'rechazado',
            'color_class' => 'bg-red-100 text-red-700',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('repair_order_statuses')->where('slug', 'rechazado')->delete();
    }
};
