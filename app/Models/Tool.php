<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Herramienta o equipo del taller. No es una refacción: no tiene stock, precio de venta
 * ni descuento automático. Dar de baja = estado "baja" (baja lógica, el registro se conserva).
 */
class Tool extends Model
{
    public const ESTADO_DISPONIBLE   = 'disponible';
    public const ESTADO_EN_USO       = 'en_uso';
    public const ESTADO_PRESTADA     = 'prestada';
    public const ESTADO_MANTENIMIENTO = 'mantenimiento';
    public const ESTADO_DANADA       = 'dañada';
    public const ESTADO_BAJA         = 'baja';

    public const ESTADOS = [
        self::ESTADO_DISPONIBLE,
        self::ESTADO_EN_USO,
        self::ESTADO_PRESTADA,
        self::ESTADO_MANTENIMIENTO,
        self::ESTADO_DANADA,
        self::ESTADO_BAJA,
    ];

    public const CONDICIONES = ['excelente', 'buena', 'regular', 'mala'];

    protected $fillable = [
        'category_id',
        'location_id',
        'name',
        'brand',
        'model',
        'serial_number',
        'internal_code',
        'purchase_date',
        'purchase_cost',
        'status',
        'condition',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'active'        => 'boolean',
    ];

    protected static function booted(): void
    {
        // active se deriva del estado: una herramienta dada de baja queda inactiva.
        static::saving(function (Tool $tool) {
            $tool->active = $tool->status !== self::ESTADO_BAJA;
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
