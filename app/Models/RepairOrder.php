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

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
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
    public function avancePhotos(): HasMany
    {
        return $this->hasMany(RepairOrderPhoto::class)->orderBy('id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->orderByDesc('kind')->orderByDesc('version');
    }

    /**
     * Bloquea la fila de la orden dentro de una transacción. Toda operación que cambie la
     * autorización (aprobar o crear principal) o registre pagos debe llamarla antes de comprobar
     * las reglas, para que dos procesos simultáneos no pasen la misma comprobación a la vez.
     * Debe llamarse dentro de DB::transaction.
     */
    public function bloquearParaCambioFinanciero(): void
    {
        static::whereKey($this->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Cotización vigente: la aprobada; si no hay, la más reciente abierta.
     */
    public function currentQuote(): ?Quote
    {
        $principales = $this->quotes()->where('kind', Quote::KIND_PRINCIPAL);

        return $principales->where('status', Quote::APROBADA)->first()
            ?? $principales->whereIn('status', [Quote::ENVIADA, Quote::BORRADOR])->first();
    }

    /** Orden en un estado de cierre (entregado o rechazado), según RepairOrderStatus::CERRADOS. */
    public function estaCerrada(): bool
    {
        return in_array($this->status?->slug, RepairOrderStatus::CERRADOS, true);
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