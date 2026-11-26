<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Subtipo de vehículo. Hoy solo existe para Motocicleta; siempre pertenece a un tipo. */
class VehicleSubtype extends Model
{
    protected $fillable = ['vehicle_type_id', 'code', 'name', 'sort', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }
}
