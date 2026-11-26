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
        'vehicle_type_id', 'vehicle_subtype_id', 'fuel_type_id', 'transmission_id',
    ];

    /**
     * Placa normalizada: sin espacios sobrantes, en mayúsculas; vacía => null.
     * Un null no entra en la unicidad, así que varios vehículos sin placa pueden coexistir.
     */
    public static function normalizarPlaca(?string $placa): ?string
    {
        $limpia = mb_strtoupper(trim((string) $placa));

        return $limpia === '' ? null : $limpia;
    }

    /** Toda escritura de placa pasa por la normalización, sin importar desde dónde se guarde. */
    public function setPlateAttribute(?string $valor): void
    {
        $this->attributes['plate'] = self::normalizarPlaca($valor);
    }

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

    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function vehicleSubtype()
    {
        return $this->belongsTo(VehicleSubtype::class);
    }

    public function fuelType()
    {
        return $this->belongsTo(FuelType::class);
    }

    public function transmission()
    {
        return $this->belongsTo(Transmission::class);
    }

    /**
     * Marca para mostrar: catálogo (relación) si existe; si no, el texto histórico de la columna.
     * Solo lectura: no escribe FK ni modifica el vehículo.
     */
    public function displayBrand(): ?string
    {
        $raw = (string) $this->getRawOriginal('brand');

        return $this->relacionCargada('brand')?->name ?? ($raw !== '' ? $raw : null);
    }

    /** Modelo para mostrar, con la misma regla que la marca. */
    public function displayModel(): ?string
    {
        $raw = (string) $this->getRawOriginal('model');

        return $this->relacionCargada('vehicleModel')?->name ?? ($raw !== '' ? $raw : null);
    }

    /**
     * Lee la relación aunque exista una columna de texto con el mismo nombre (brand, model).
     * Sin esto, $this->brand devuelve el texto histórico y el catálogo nunca se muestra.
     */
    private function relacionCargada(string $relacion): mixed
    {
        return $this->relationLoaded($relacion)
            ? $this->getRelation($relacion)
            : $this->$relacion()->first();
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