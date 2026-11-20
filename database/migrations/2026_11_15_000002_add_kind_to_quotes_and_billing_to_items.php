<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('kind', 12)->default('principal')->after('version');
            $table->dropUnique('quotes_repair_order_id_version_unique');
            $table->unique(['repair_order_id', 'kind', 'version']);
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->unsignedBigInteger('order_item_id')->nullable()->after('quote_id');
        });

        Schema::table('repair_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('billed_quote_id')->nullable();
        });

        // Cotizaciones ya aprobadas antes de esta migración: sus conceptos quedan facturados
        DB::table('quotes')
            ->where('status', 'aprobada')
            ->orderBy('id')
            ->get(['id', 'repair_order_id'])
            ->each(function ($quote) {
                DB::table('repair_order_items')
                    ->where('repair_order_id', $quote->repair_order_id)
                    ->whereNull('billed_quote_id')
                    ->update(['billed_quote_id' => $quote->id]);
            });
    }

    public function down(): void
    {
        Schema::table('repair_order_items', function (Blueprint $table) {
            $table->dropColumn('billed_quote_id');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn('order_item_id');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropUnique(['repair_order_id', 'kind', 'version']);
            $table->unique(['repair_order_id', 'version']);
            $table->dropColumn('kind');
        });
    }
};
