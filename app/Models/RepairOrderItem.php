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
    ];

    // Cantidad con dos decimales como texto exacto ("1.50"): evita errores de punto flotante.
    // Refacciones: enteras (validado en addItem). Mano de obra: fraccionaria (p. ej. 1.50 h).
    protected $casts = [
        'quantity' => 'decimal:2',
    ];

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