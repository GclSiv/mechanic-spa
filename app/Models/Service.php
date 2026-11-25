<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Servicio del catálogo con comisión fija por ejecución.
 * Los conceptos guardan su propio snapshot: cambiar commission_amount o el nombre NO altera históricos.
 * Un servicio inactivo no se elige en conceptos nuevos, pero sigue resolviéndose en históricos.
 */
class Service extends Model
{
    protected $fillable = ['name', 'commission_amount', 'is_active'];

    protected $casts = [
        'commission_amount' => 'decimal:2',
        'is_active'         => 'boolean',
    ];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RepairOrderItem::class);
    }
}
