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
    'miles',
    'fuel_level',
    'technical_symptoms',
    'brand_id',
    'vehicle_model_id',
    'status'
];

// Relación con Marcas
public function brand() {
    return $this->belongsTo(Brand::class);
}

public function vehicleModel() {
    // Laravel convertirá automáticamente 'vehicleModel' a 'vehicle_model' en el JSON
    return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
}
}
