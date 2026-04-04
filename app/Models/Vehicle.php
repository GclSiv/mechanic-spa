<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'brand_id', 'model_id',
        'brand', 'model', 
        'year', 'plate', 'vin', 'engine',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    // 👇 ASEGÚRATE DE TENER ESTAS DOS RELACIONES 👇
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function vehicleModel()
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }
}