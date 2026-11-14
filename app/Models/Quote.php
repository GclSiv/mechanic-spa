<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Quote extends Model
{
    use HasFactory;

    public const BORRADOR   = 'borrador';
    public const ENVIADA    = 'enviada';
    public const APROBADA   = 'aprobada';
    public const RECHAZADA  = 'rechazada';
    public const REEMPLAZADA = 'reemplazada';

    public const METHODS = ['presencial', 'telefono', 'whatsapp'];

    protected $fillable = [
        'repair_order_id', 'version', 'folio', 'status', 'tax_rate',
        'parts_subtotal', 'labor_subtotal', 'subtotal', 'tax', 'total',
        'valid_until', 'notes', 'sent_at', 'approved_at', 'approval_method',
        'approved_by_name', 'rejected_at', 'rejection_reason',
    ];

    protected $casts = [
        'tax_rate'        => 'float',
        'parts_subtotal'  => 'float',
        'labor_subtotal'  => 'float',
        'subtotal'        => 'float',
        'tax'             => 'float',
        'total'           => 'float',
        'valid_until'     => 'date',
        'sent_at'         => 'datetime',
        'approved_at'     => 'datetime',
        'rejected_at'     => 'datetime',
    ];

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('id');
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && Carbon::today()->gt($this->valid_until);
    }

    /**
     * Marca la cotización como enviada al cliente. Solo desde borrador.
     */
    public function markSent(): void
    {
        if ($this->status !== self::BORRADOR) {
            return;
        }
        $this->update(['status' => self::ENVIADA, 'sent_at' => now()]);
    }

    /**
     * Registra la aprobación y reemplaza las demás cotizaciones abiertas de la orden.
     */
    public function approve(string $method, string $byName): void
    {
        $this->update([
            'status'          => self::APROBADA,
            'approved_at'     => now(),
            'approval_method' => $method,
            'approved_by_name'=> $byName,
        ]);

        static::where('repair_order_id', $this->repair_order_id)
            ->where('id', '!=', $this->id)
            ->whereIn('status', [self::BORRADOR, self::ENVIADA])
            ->update(['status' => self::REEMPLAZADA]);
    }

    public function reject(?string $reason): void
    {
        $this->update([
            'status'           => self::RECHAZADA,
            'rejected_at'      => now(),
            'rejection_reason' => $reason,
        ]);
    }
}
