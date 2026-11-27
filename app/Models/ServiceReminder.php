<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceReminder extends Model
{
    public const PENDIENTE  = 'pendiente';
    public const COMPLETADO = 'completado';
    public const CANCELADO  = 'cancelado';

    protected $fillable = [
        'client_id', 'vehicle_id', 'repair_order_id',
        'title', 'recommended_date', 'recommended_mileage', 'notes', 'status',
    ];

    protected $casts = [
        'recommended_date' => 'date',
        'notified_at'      => 'datetime',
    ];

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
