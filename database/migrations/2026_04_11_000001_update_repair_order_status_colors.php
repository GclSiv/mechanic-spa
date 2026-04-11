<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('repair_order_statuses')->where('slug', 'recibido')->update([
            'color_class' => 'bg-gray-100 text-gray-700',
        ]);
        DB::table('repair_order_statuses')->where('slug', 'diagnostico')->update([
            'color_class' => 'bg-blue-100 text-blue-700',
        ]);
        DB::table('repair_order_statuses')->where('slug', 'espera-piezas')->update([
            'color_class' => 'bg-yellow-100 text-yellow-800',
        ]);
        DB::table('repair_order_statuses')->where('slug', 'reparado')->update([
            'color_class' => 'bg-green-100 text-green-700',
        ]);
        DB::table('repair_order_statuses')->where('slug', 'entregado')->update([
            'color_class' => 'bg-emerald-500 text-white',
        ]);
    }

    public function down(): void
    {
        DB::table('repair_order_statuses')->where('slug', 'recibido')->update(['color_class' => 'bg-gray-500']);
        DB::table('repair_order_statuses')->where('slug', 'diagnostico')->update(['color_class' => 'bg-yellow-500']);
        DB::table('repair_order_statuses')->where('slug', 'espera-piezas')->update(['color_class' => 'bg-orange-500']);
        DB::table('repair_order_statuses')->where('slug', 'reparado')->update(['color_class' => 'bg-green-500']);
        DB::table('repair_order_statuses')->where('slug', 'entregado')->update(['color_class' => 'bg-blue-600']);
    }
};
