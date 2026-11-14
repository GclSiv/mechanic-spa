<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Número de cliente visible (CLI-0001) para identificarlo en recepciones, PDFs y búsquedas.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'client_number')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('client_number', 20)->nullable()->unique()->after('id');
            });
        }

        // Asignar número a los clientes que ya existen
        DB::table('clients')->whereNull('client_number')->orderBy('id')->each(function ($client) {
            DB::table('clients')->where('id', $client->id)
                ->update(['client_number' => 'CLI-' . str_pad((string) $client->id, 4, '0', STR_PAD_LEFT)]);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'client_number')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropUnique(['client_number']);
                $table->dropColumn('client_number');
            });
        }
    }
};
