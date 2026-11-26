<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recepcion extends Model
{
    use HasFactory;

    /**
     * Los atributos transaccionales puros.
     * Observa que quitamos los datos del cliente/vehículo y agregamos los IDs.
     */
    protected $fillable = [
        'client_id',
        'vehicle_id',
        'fuel_level',
        'miles',
        'reading_type',
        'reading_value',
        'tire_size',
        'symptoms',
        'witnesses',
        'inventory',
        'damages',
        'damage_diagram',
        'photos',
    ];

    /**
     * Casts para manejar correctamente los JSON en la base de datos
     */
    protected $casts = [
        'witnesses' => 'array',
        'inventory' => 'array',
        'damages'   => 'array',
        'photos'    => 'array',
    ];

    /**
     * Relación: La recepción pertenece a un Cliente.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Relación: La recepción pertenece a un Vehículo.
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function repairOrder()
    {
        return $this->hasOne(\App\Models\RepairOrder::class, 'recepcion_id');
    }

    /** Elementos recibidos (Recepción V2), en el orden en que se capturaron. */
    public function items()
    {
        return $this->hasMany(RecepcionItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Kilometraje COMPARABLE entre visitas: solo lecturas registradas explícitamente en km.
     * Millas, horas y el valor histórico de miles (unidad desconocida) no cuentan: no se convierten ni se asumen.
     */
    public function kilometrajeKm(): ?int
    {
        if ($this->reading_type === 'km' && $this->reading_value !== null) {
            return (int) round((float) $this->reading_value);
        }

        return null;
    }

    /**
     * Lectura para mostrar, con su unidad (en inglés, como el resto del PDF). Null si no hay dato.
     * Un valor histórico sin tipo se muestra tal cual, marcando que la unidad no está especificada.
     */
    public function lecturaPdf(): ?string
    {
        $unidades = ['km' => 'km', 'mi' => 'mi', 'hr' => 'hr'];

        if ($this->reading_type && $this->reading_value !== null && isset($unidades[$this->reading_type])) {
            return rtrim(rtrim(number_format((float) $this->reading_value, 2, '.', ','), '0'), '.') . ' ' . $unidades[$this->reading_type];
        }

        return $this->lecturaHistoricaTexto() === null
            ? null
            : $this->lecturaHistoricaTexto() . ' · unit not specified';
    }

    /** Valor histórico de miles sin convertir ni asignar unidad. Null si no hay valor. */
    public function lecturaHistoricaTexto(): ?string
    {
        if ($this->reading_type !== null) {
            return null;
        }

        $legado = trim((string) $this->miles);
        if ($legado === '') {
            return null;
        }

        // Solo enteros se agrupan con separador; cualquier otro texto se muestra exactamente como se guardó.
        $esEntero = is_numeric($legado) && fmod((float) $legado, 1.0) === 0.0;

        return $esEntero ? number_format((float) $legado, 0, '.', ',') : $legado;
    }
}