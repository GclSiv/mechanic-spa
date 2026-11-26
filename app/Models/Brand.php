<?php

namespace App\Models;

use App\Support\NombreClave;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    /** @use HasFactory<\Database\Factories\BrandFactory> */
    use HasFactory;

    protected $fillable = ['name'];

    protected static function booted(): void
    {
        // La clave se deriva del nombre en cada escritura: nunca se captura a mano.
        static::saving(function (Brand $brand) {
            $brand->name = trim((string) $brand->name);
            $brand->name_key = NombreClave::de($brand->name);
        });
    }

    public function vehicleModels(): HasMany
    {
        return $this->hasMany(VehicleModel::class);
    }
}
