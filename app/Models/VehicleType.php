<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tipo de vehículo (catálogo). damage_diagram se registra para Fase E; hoy no dibuja nada. */
class VehicleType extends Model
{
    protected $fillable = ['code', 'name', 'damage_diagram', 'sort', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function subtypes(): HasMany
    {
        return $this->hasMany(VehicleSubtype::class);
    }
}
