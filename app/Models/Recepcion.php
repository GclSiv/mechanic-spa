<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recepcion extends Model
{
    /**
     * Los atributos transaccionales puros.
     * Observa que quitamos los datos del cliente/vehículo y agregamos los IDs.
     */
    protected $fillable = [
        'client_id',     // NUEVO: Llave foránea del cliente
        'vehicle_id',    // NUEVO: Llave foránea del vehículo
        'fuel_level',
        'miles',
        'symptoms',
        'witnesses',
        'inventory',
        'photos',        // Preparado para las evidencias gráficas
        'status',
    ];

    /**
     * Casts para manejar correctamente los JSON en la base de datos
     */
    protected $casts = [
        'witnesses' => 'array',
        'inventory' => 'array',
        'photos'    => 'array',
    ];

    /**
     * Relación: La recepción pertenece a un Cliente.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Relación: La recepción pertenece a un Vehículo.
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}