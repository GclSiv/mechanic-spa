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
    'photos',

    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     */
    protected $casts = [
        //'photos'    => 'array',
        'witnesses' => 'array',
        'inventory' => 'array',
         
    ];

    // Esto asegura que si no hay fotos, siempre devuelva un array vacío [] y no un null
public function getPhotosAttribute($value)
{
    return json_decode($value) ?: [];
}

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

    
    public function repairOrder()
{
    // Una Recepción tiene una (hasOne) Orden de Reparación
    return $this->hasOne(RepairOrder::class, 'recepcion_id');
}



}