<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentRequest extends Model
{
    public const PENDIENTE  = 'pendiente';
    public const CONFIRMADA = 'confirmada';
    public const RECHAZADA  = 'rechazada';
    public const CANCELADA  = 'cancelada';
    public const COMPLETADA = 'completada';

    /** Estados que bloquean una nueva solicitud del mismo vehículo. */
    public const ACTIVAS = [self::PENDIENTE, self::CONFIRMADA];

    public const MOTIVOS = ['mantenimiento', 'revision', 'garantia', 'diagnostico', 'otro'];
    public const HORARIOS = ['manana', 'tarde'];

    /** Transiciones permitidas. Un estado final no tiene salidas. */
    private const TRANSICIONES = [
        self::PENDIENTE  => [self::CONFIRMADA, self::RECHAZADA, self::CANCELADA],
        self::CONFIRMADA => [self::COMPLETADA, self::CANCELADA],
        self::RECHAZADA  => [],
        self::CANCELADA  => [],
        self::COMPLETADA => [],
    ];

    protected $fillable = [
        'client_id', 'vehicle_id', 'repair_order_id',
        'preferred_date', 'preferred_time', 'reason', 'notes',
        'status', 'confirmed_date', 'confirmed_time', 'customer_note', 'admin_notes',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'confirmed_date' => 'date',
    ];

    public function scopeActivas(Builder $consulta): Builder
    {
        return $consulta->whereIn('status', self::ACTIVAS);
    }

    public function estaActiva(): bool
    {
        return in_array($this->status, self::ACTIVAS, true);
    }

    public function puedeTransicionarA(string $nuevo): bool
    {
        return in_array($nuevo, self::TRANSICIONES[$this->status] ?? [], true);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }
}
