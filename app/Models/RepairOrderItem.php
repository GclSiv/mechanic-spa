<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairOrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\RepairOrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'billed_quote_id',
        'repair_order_id',
        'part_id',
        'description',
        'quantity',
        'unit_price',
        'subtotal',
        'type',
        'service_id',
        'mechanic_id',
    ];

    // commission_model y commission_amount_snapshot NO son fillable: son derivados del dominio y solo
    // se asignan internamente (RepairOrderController@addItem, con forceFill). Nunca desde la petición.

    // Modelo de comisión: histórico (legacy) o fijo por concepto (fixed). Nunca se exponen a la pantalla.
    public const MODELO_LEGACY = 'legacy';
    public const MODELO_FIXED  = 'fixed';

    protected $hidden = ['commission_model', 'commission_amount_snapshot'];

    // Cantidad con dos decimales como texto exacto ("1.50"): evita errores de punto flotante.
    // Refacciones: enteras (validado en addItem). Mano de obra: fraccionaria (p. ej. 1.50 h).
    protected $casts = [
        'quantity' => 'decimal:2',
        'commission_amount_snapshot' => 'decimal:2',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    // Mecánico ejecutor del concepto (mano de obra nueva). Histórico: null.
    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    // Relación inversa: Un item pertenece a una Orden de Reparación
    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }
}