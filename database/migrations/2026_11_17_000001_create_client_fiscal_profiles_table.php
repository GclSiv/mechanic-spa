<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un cliente puede tener varios perfiles fiscales (varias empresas o RFC).
     * Los RFC genéricos (XAXX010101000 / XEXX010101000) no se guardan como perfil.
     */
    public function up(): void
    {
        Schema::create('client_fiscal_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('rfc', 13)->index();
            $table->string('razon_social')->nullable();
            $table->timestamps();
        });

        // Respaldo: el RFC que ya tenían los clientes pasa a ser su primer perfil fiscal
        $ahora = now();
        DB::table('clients')
            ->whereNotNull('rfc')
            ->where('rfc', '!=', '')
            ->orderBy('id')
            ->each(function ($cliente) use ($ahora) {
                if (in_array(strtoupper($cliente->rfc), ['XAXX010101000', 'XEXX010101000'], true)) {
                    return;
                }
                DB::table('client_fiscal_profiles')->insert([
                    'client_id'    => $cliente->id,
                    'rfc'          => strtoupper(trim($cliente->rfc)),
                    'razon_social' => trim(($cliente->first_name ?? '') . ' ' . ($cliente->last_name ?? '')),
                    'created_at'   => $ahora,
                    'updated_at'   => $ahora,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_fiscal_profiles');
    }
};
