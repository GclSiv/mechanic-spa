<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Transmisión del vehículo (catálogo). */
class Transmission extends Model
{
    protected $fillable = ['code', 'name', 'sort', 'active'];

    protected $casts = ['active' => 'boolean'];
}
