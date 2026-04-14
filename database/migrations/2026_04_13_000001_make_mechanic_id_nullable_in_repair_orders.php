<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            // Primero se elimina la FK, luego se modifica y se recrea nullable
            $table->dropForeign(['mechanic_id']);
            $table->unsignedBigInteger('mechanic_id')->nullable()->change();
            $table->foreign('mechanic_id')->references('id')->on('mechanics')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropForeign(['mechanic_id']);
            $table->unsignedBigInteger('mechanic_id')->nullable(false)->change();
            $table->foreign('mechanic_id')->references('id')->on('mechanics');
        });
    }
};
