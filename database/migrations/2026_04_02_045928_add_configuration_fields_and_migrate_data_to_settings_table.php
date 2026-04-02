<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Añadir las nuevas columnas a 'settings'
        Schema::table('settings', function (Blueprint $table) {
            // Añadimos tax_type para identificar el país y aplicar la regla del 8.75% en Vue
            $table->string('tax_type', 10)->default('MX')->after('company_name'); 
            $table->decimal('iva', 5, 2)->default(16.00)->after('tax_type');
            $table->string('razon_social', 500)->nullable()->after('iva');
            // En US, el campo 'rfc' se puede usar para guardar su Tax ID (EIN)
            $table->string('rfc', 200)->nullable()->after('email'); 
        });

        // 2. Migración segura de datos en una transacción
        DB::transaction(function () {
            // Obtenemos todos los registros de configurations
            $configurations = DB::table('configurations')->get();

            foreach ($configurations as $config) {
                //$setting = DB::table('settings')->where('id', $config->id)->first();
                $setting = DB::table('settings')
    ->where('email', $config->email)
    ->orWhere('company_name', $config->razon_social)
    ->first();
                // Lógica inteligente: Si el IVA viejo era 8.75, asignamos región US
                $taxRate = $config->iva ?? 16.00;
                $taxType = ($taxRate == 8.75) ? 'US' : 'MX';//modificar si se requiere otro pais

                if ($setting) {
                    // Smart Merge: Actualizamos los campos nuevos
                    DB::table('settings')->where('id', $config->id)->update([
                        'tax_type'     => $taxType,
                        'iva'          => $taxRate,
                        'razon_social' => $config->razon_social,
                        'rfc'          => $config->rfc,
                        // Preservamos datos de settings si existen, si no, usamos los de configuration
                        'address'      => $setting->address ?: $config->address,
                        'phone'        => $setting->phone ?: $config->phone,
                        'email'        => $setting->email ?: $config->email,
                    ]);
                } else {
                    // Si no existía en settings, lo creamos completo
                    DB::table('settings')->insert([
                       
                        'company_name'    => $config->razon_social ?? 'Empresa Genérica',
                        'tax_type'        => $taxType,
                        'iva'             => $taxRate,
                        'razon_social'    => $config->razon_social,
                        'rfc'             => $config->rfc,
                        'address'         => $config->address,
                        'phone'           => $config->phone,
                        'email'           => $config->email,
                        'primary_color'   => '#10213E', // Valores requeridos de tu esquema original
                        'secondary_color' => '#EE2857',
                        'accent_color'    => '#68C6BA',
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('configurations', function (Blueprint $table) {
        $table->id();
        $table->decimal('iva', 5, 2)->nullable();
        $table->string('razon_social')->nullable();
        $table->string('address')->nullable();
        $table->string('phone')->nullable();
        $table->string('email')->nullable();
        $table->string('rfc')->nullable();
        $table->timestamps();
    });
    }
};