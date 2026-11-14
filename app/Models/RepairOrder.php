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
        'needs_quote',
        'status_id', 
        'problem_description', 
        'estimated_cost', 
        'tax_rate',
        'entry_date',
        'delivery_date',
        'is_paid',
    ];

    protected $casts = [
        'is_paid'     => 'boolean',
        'needs_quote' => 'boolean',
        'tax_rate'    => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (RepairOrder $order) {
            if (empty($order->tracking_token)) {
                $order->tracking_token = \Illuminate\Support\Str::random(48);
            }
        });
    }

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
     * Cotizaciones de la orden (una nueva por cada hallazgo adicional).
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->orderByDesc('version');
    }

    /**
     * Cotización vigente: la aprobada; si no hay, la más reciente abierta.
     */
    public function currentQuote(): ?Quote
    {
        return $this->quotes()->where('status', Quote::APROBADA)->first()
            ?? $this->quotes()->whereIn('status', [Quote::ENVIADA, Quote::BORRADOR])->first();
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    // se Puede agregar relaciones adicionales para Client y Vehicle si las necesitas
   
}