<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // 2026_03_25_xxxxxx_create_repair_order_items_table.php

public function up(): void
{
    Schema::create('repair_order_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('repair_order_id')->constrained()->onDelete('cascade');
        
        // Si el item es una refacción de tu tabla 'parts', guardamos el ID
        $table->foreignId('part_id')->nullable()->constrained('parts'); 
        
        $table->string('description'); // Ej: "Mano de obra frenos" o el nombre de la pieza
        $table->integer('quantity')->default(1);
        $table->decimal('unit_price', 12, 2);
        $table->decimal('subtotal', 12, 2);
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repair_order_items');
    }
};