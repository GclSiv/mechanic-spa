<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tipo de combustible del vehículo. Distinto del nivel de combustible de la recepción. */
class FuelType extends Model
{
    protected $fillable = ['code', 'name', 'sort', 'active'];

    protected $casts = ['active' => 'boolean'];
}
