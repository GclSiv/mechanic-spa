<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleFactory> */
    protected $fillable = ['client_id', 'plate', 'brand', 'model', 'year', 'color', 'notes'];
    use HasFactory;
}
