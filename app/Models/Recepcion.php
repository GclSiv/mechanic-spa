<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recepcion extends Model
{
    /** @use HasFactory<\Database\Factories\RecepcionFactory> */
    use HasFactory;

    protected $fillable = [
        
     'first_name',
    'phone',
    'address',
    'rfc',
    'brand_id',
    'vehicle_model_id',
    'year',
    'plate',
    'vin_serial',
    'miles',
    'fuel_level',
    'symptoms',
    'witnesses',
    'inventory',
    'status',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     */
    protected $casts = [
        'witnesses' => 'array',
        'inventory' => 'array',
    ];

    // Relación con Marcas
    public function brand() 
    {
        return $this->belongsTo(Brand::class);
    }

    // Relación con Modelos de Vehículo
    public function vehicleModel() 
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }
}