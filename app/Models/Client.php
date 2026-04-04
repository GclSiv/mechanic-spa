<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory; // 2. Activar el chip dentro de la clase
    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'address', // Campos correctos de identidad
    ];

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function repairOrders()
    {
        return $this->hasMany(RepairOrder::class);
    }
}