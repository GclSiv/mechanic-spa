<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'brand_id', 'model_id',
        'brand', 'model', 
        'year', 'plate', 'vin', 'engine', 'color',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    // 👇 ASEGÚRATE DE TENER ESTAS DOS RELACIONES 👇
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function vehicleModel()
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    /**
     * Marca para mostrar: catálogo (relación) si existe; si no, el texto histórico de la columna.
     * Solo lectura: no escribe FK ni modifica el vehículo.
     */
    public function displayBrand(): ?string
    {
        $raw = (string) $this->getRawOriginal('brand');

        return $this->brand?->name ?? ($raw !== '' ? $raw : null);
    }

    /** Modelo para mostrar, con la misma regla que la marca. */
    public function displayModel(): ?string
    {
        $raw = (string) $this->getRawOriginal('model');

        return $this->vehicleModel?->name ?? ($raw !== '' ? $raw : null);
    }

    /**
     * Adjunta marca y modelo ya resueltos para la vista (display_brand / display_model).
     * Se usa en Index y Show; no escribe en la base de datos.
     */
    public function withDisplay(): static
    {
        $this->setAttribute('display_brand', $this->displayBrand());
        $this->setAttribute('display_model', $this->displayModel());

        return $this;
    }

    /** Recepciones de este vehículo (FK recepcions.vehicle_id). */
    public function recepciones()
    {
        return $this->hasMany(Recepcion::class);
    }
}