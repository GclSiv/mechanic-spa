<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
{
    Schema::table('recepcions', function (Blueprint $table) {
        $table->string('phone')->nullable()->after('customer_name');
        $table->string('miles')->nullable()->after('vin_serial');
        $table->json('witnesses')->nullable()->after('symptoms'); // Para los iconos del tablero
        $table->json('inventory')->nullable()->after('witnesses'); // Para la llanta, gato, etc.
        $table->foreignId('brand_id')->nullable()->constrained();
        $table->foreignId('vehicle_model_id')->nullable()->constrained();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recepcions', function (Blueprint $table) {
            //
        });
    }
};
