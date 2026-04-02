<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Preparar la tabla vehicles
        Schema::table('vehicles', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicles', 'vin')) {
                $table->string('vin', 100)->nullable()->after('year');
            }
            if (!Schema::hasColumn('vehicles', 'engine')) {
                $table->string('engine', 100)->nullable()->after('vin');
            }
        });

        // 2. Transferencia de datos segura
        DB::transaction(function () {
            $clients = DB::table('clients')->get();

            foreach ($clients as $client) {
                // A. Mover datos al Vehículo
                if (!empty($client->vin) || !empty($client->engine)) {
                    $vehicle = DB::table('vehicles')->where('client_id', $client->id)->first();

                    if ($vehicle) {
                        // Smart Merge: Solo actualiza si el vehículo no tenía estos datos
                        DB::table('vehicles')->where('id', $vehicle->id)->update([
                            'vin'    => $vehicle->vin ?: $client->vin,
                            'engine' => $vehicle->engine ?: $client->engine,
                        ]);
                    } else {
                        // Rescate de datos: Crear vehículo base si no existía
                        DB::table('vehicles')->insert([
                            'client_id'  => $client->id,
                            'vin'        => $client->vin,
                            'engine'     => $client->engine,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                // B. Mover datos Transaccionales (Recepciones)
                if (!empty($client->fuel_level) || !empty($client->miles) || !empty($client->technical_symptoms)) {
                    // Buscamos la última orden de reparación de este cliente
                    $repairOrder = DB::table('repair_orders')->where('client_id', $client->id)->latest('id')->first();

                    if ($repairOrder && $repairOrder->recepcion_id) {
                        $recepcion = DB::table('recepcions')->where('id', $repairOrder->recepcion_id)->first();
                        
                        if ($recepcion) {
                            DB::table('recepcions')->where('id', $recepcion->id)->update([
                                'fuel_level' => $recepcion->fuel_level ?: $client->fuel_level,
                                'miles'      => $recepcion->miles ?: $client->miles,
                                'symptoms'   => $recepcion->symptoms ?: $client->technical_symptoms,
                            ]);
                        }
                    }
                    // Nota de Arquitectura: Si el cliente tiene síntomas registrados en `clients` 
                    // pero no tiene historial de órdenes, esos datos transaccionales huérfanos 
                    // se perderán lógicamente, ya que no hay una orden física a la cual atarlos.
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['vin', 'engine']);
        });
    }
};