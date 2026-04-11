<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepairOrder extends Model
{
    /** @use HasFactory<\Database\Factories\RepairOrderFactory> */
    use HasFactory;

    // Agregamos 'recepcion_id' al fillable para vincular la entrada con la orden
    protected $fillable = [
        'recepcion_id', // Vínculo vital para el PDF
        'folio', 
        
        'client_id', 
        'vehicle_id', 
        'mechanic_id', 
        'status_id', 
        'problem_description', 
        'estimated_cost', 
        'entry_date',
        'delivery_date'
    ];

    /**
     * Relación con la Recepción original de JK AUTOMOTIVE CARE INC.
     * Permite obtener los síntomas y fotos iniciales.
     */
    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(Recepcion::class);
    }

    /**
     * Relación con los items (refacciones y mano de obra) de la cotización.
     */
    public function items(): HasMany
    {
        return $this->hasMany(RepairOrderItem::class);
    }

    /**
     * Relación con el estado de la orden (Cotización, En Proceso, etc.)
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(RepairOrderStatus::class, 'status_id');
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class)->latest('date');
    }

    // se Puede agregar relaciones adicionales para Client y Vehicle si las necesitas
   
}