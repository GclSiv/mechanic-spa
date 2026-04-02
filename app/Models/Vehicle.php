<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'client_id', 'brand_id', 'model_id', 'year', 'plate', 
        'vin', 'engine' // Nuevos campos estáticos
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}