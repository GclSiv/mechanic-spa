<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar user_id y email a mechanics
        Schema::table('mechanics', function (Blueprint $table) {
            $table->string('email')->unique()->nullable()->after('name');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->after('email');
        });

        // 2. Insertar nuevos tipos de mecánico
        DB::table('mechanic_types')->insert([
            ['name' => 'Hojalatería',     'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Pintura',         'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mecánico Diésel', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::table('mechanics', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['email', 'user_id']);
        });

        DB::table('mechanic_types')
            ->whereIn('name', ['Hojalatería', 'Pintura', 'Mecánico Diésel'])
            ->delete();
    }
};
