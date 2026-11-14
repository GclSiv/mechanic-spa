<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_id', 'part_id', 'type', 'description',
        'quantity', 'unit_price', 'subtotal',
    ];

    protected $casts = [
        'quantity'   => 'float',
        'unit_price' => 'float',
        'subtotal'   => 'float',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
