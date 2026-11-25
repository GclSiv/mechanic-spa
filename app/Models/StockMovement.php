<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Movimiento de inventario (kardex). Inmutable: se corrige con un movimiento nuevo, nunca editando el anterior.
 */
class StockMovement extends Model
{
    public const TIPO_INICIAL  = 'inicial';
    public const TIPO_ENTRADA  = 'entrada';
    public const TIPO_SALIDA   = 'salida';
    public const TIPO_AJUSTE   = 'ajuste';
    public const TIPO_CONSUMO  = 'consumo_orden';
    public const TIPO_REVERSA  = 'reversa_orden';

    /** Tipos que puede registrar un admin/owner desde el Kardex. */
    public const TIPOS_MANUALES = [self::TIPO_ENTRADA, self::TIPO_SALIDA, self::TIPO_AJUSTE];

    protected $fillable = [
        'part_id', 'type', 'quantity', 'stock_before', 'stock_after',
        'user_id', 'repair_order_item_id', 'reason', 'idempotency_key',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de inventario son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los movimientos de inventario no se eliminan.'));
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
