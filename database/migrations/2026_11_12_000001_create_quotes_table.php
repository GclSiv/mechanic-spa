<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cotizaciones. Una orden puede tener varias (una por cada hallazgo nuevo).
     * Los montos se guardan como snapshot: no cambian si después se editan los conceptos de la orden.
     */
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('folio', 40)->unique();
            // borrador | enviada | aprobada | rechazada | reemplazada
            $table->string('status', 20)->default('borrador');
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('parts_subtotal', 12, 2)->default(0);
            $table->decimal('labor_subtotal', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            // Cómo se confirmó la aprobación: presencial, teléfono, whatsapp
            $table->string('approval_method', 20)->nullable();
            $table->string('approved_by_name', 120)->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['repair_order_id', 'version']);
            $table->index(['repair_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
