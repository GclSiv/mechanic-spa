<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// UI-8B: kardex. Libro de movimientos de refacciones; filas inmutables (el modelo bloquea update/delete).
// Restrict en parts: el historial nunca desaparece por borrar una refacción.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained('parts')->restrictOnDelete();
            $table->string('type', 20);
            $table->integer('quantity');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('repair_order_item_id')->nullable()->constrained('repair_order_items')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->timestamps();

            $table->index(['part_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
