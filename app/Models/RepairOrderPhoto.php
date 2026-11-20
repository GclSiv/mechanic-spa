<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairOrderPhoto extends Model
{
    protected $fillable = ['repair_order_id', 'path', 'note'];

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }
}
