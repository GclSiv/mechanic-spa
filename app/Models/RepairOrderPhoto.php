<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairOrderPhoto extends Model
{
    protected $fillable = ['repair_order_id', 'path', 'note'];

    // La ruta interna de storage no sale a la vista: las imágenes usan la ruta protegida de la orden.
    protected $hidden = ['path'];

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }
}
