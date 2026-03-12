<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('repair_order_statuses', function (Blueprint $table) {
            $table->id();
           
        $table->string('name');          // Ejemplo: 'En Proceso'
        $table->string('slug')->unique(); // Ejemplo: 'en-proceso'
        $table->string('color_class');   // Ejemplo: 'bg-blue-500' (Para Tailwind)
        
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repair_order_statuses');
    }
};
