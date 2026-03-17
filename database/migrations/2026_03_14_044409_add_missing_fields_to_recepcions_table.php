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
    Schema::table('recepcions', function (Blueprint $table) {
        $table->string('address')->nullable()->after('phone');
        $table->string('rfc', 20)->nullable()->after('address');
        $table->string('year', 4)->nullable()->after('vehicle_model_id');
        $table->string('plate', 20)->nullable()->after('year');
    });
}

public function down(): void
{
    Schema::table('recepcions', function (Blueprint $table) {
        $table->dropColumn(['address', 'rfc', 'year', 'plate']);
    });
}
};
