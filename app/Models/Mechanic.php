<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mechanic extends Model
{
    /** @use HasFactory<\Database\Factories\MechanicFactory> */
    protected $fillable = ['name', 'gender_id', 'mechanic_type_id', 'phone', 'is_active'];
    use HasFactory;
}
