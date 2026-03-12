<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */

    use HasFactory;
      protected $fillable = [
    'first_name',
    'last_name',
    'phone',
    'client_status_id',
    'brand_id',
    'vehicle_model_id',
    'year',
    'plate',
    'engine',
    'vin',
    'miles', // Usamos solo este para el odómetro
    'description',
    'address', // Sin espacio inicial
    'rfc',     // Sin espacio inicial
    // --- NUEVOS CAMPOS ---
    'fuel_level',
    'technical_symptoms',
    'status'
        
    ];
   
    public function vehicles()
{
    return $this->hasMany(Vehicle::class);
}

public function brand()
{
    return $this->belongsTo(Brand::class);
}

public function vehicleModel()
{
    // Asegúrate de que el nombre de la clase sea VehicleModel o el que uses
    return $this->belongsTo(VehicleModel::class); 
}
}
