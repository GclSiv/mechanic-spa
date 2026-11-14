<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correo del cliente (opcional). Lo usa la importación y los avisos al cliente.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'email')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('email')->nullable()->after('last_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'email')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }
    }
};
