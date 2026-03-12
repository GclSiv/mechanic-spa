<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name'); // 👈 ¡ESTA ES LA PIEZA QUE FALTABA!
            $table->string('phone');
            $table->string('address')->nullable();
            $table->string('rfc')->nullable();
            
            $table->foreignId('client_status_id')->default(1)->constrained(); 

            // Relaciones con vehículos (Asegúrate de que estas tablas existan antes)
            $table->foreignId('brand_id')->nullable()->constrained(); 
            $table->foreignId('vehicle_model_id')->nullable()->constrained();
            
            $table->string('year')->nullable();
            $table->string('plate')->nullable();
            $table->string('engine')->nullable();      
            $table->string('vin')->nullable();         
            $table->string('miles')->nullable();       
            $table->text('description')->nullable();   
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};