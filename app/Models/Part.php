<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sku', 'cost_price', 'sale_price', 'stock', 'low_stock_threshold'];

    protected $appends = ['is_low_stock'];

    public function getIsLowStockAttribute(): bool
    {
        return $this->stock <= $this->low_stock_threshold;
    }
}
