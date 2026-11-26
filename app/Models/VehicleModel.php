<?php

namespace App\Models;

use App\Support\NombreClave;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleModel extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleModelFactory> */
    use HasFactory;

    protected $fillable = ['brand_id', 'name'];

    protected static function booted(): void
    {
        static::saving(function (VehicleModel $modelo) {
            $modelo->name = trim((string) $modelo->name);
            $modelo->name_key = NombreClave::de($modelo->name);
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
