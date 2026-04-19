<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = ['repair_order_id', 'amount', 'payment_method', 'notes'];

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }
}
