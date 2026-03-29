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
        'repair_order_id',
        'part_id',
        'description',
        'quantity',
        'unit_price',
        'subtotal'
    ];

    // Relación inversa: Un item pertenece a una Orden de Reparación
    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }
}