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

    public const KIND_PRINCIPAL = 'principal';
    public const KIND_ADICIONAL = 'adicional';

    public const METHODS = ['presencial', 'telefono', 'whatsapp'];

    protected $fillable = [
        'repair_order_id', 'version', 'kind', 'folio', 'status', 'tax_rate',
        'parts_subtotal', 'labor_subtotal', 'subtotal', 'tax', 'total',
        'valid_until', 'notes', 'sent_at', 'approved_at', 'approval_method',
        'approved_by_name', 'rejected_at', 'rejection_reason',
        'client_accepted_name', 'client_accepted_at', 'client_accepted_ip',
        'client_accepted_agent', 'client_acceptance_hash',
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
        'client_accepted_at' => 'datetime',
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

        // Solo reemplaza cotizaciones del mismo tipo: una adicional no cancela a la principal
        static::where('repair_order_id', $this->repair_order_id)
            ->where('kind', $this->kind)
            ->where('id', '!=', $this->id)
            ->whereIn('status', [self::BORRADOR, self::ENVIADA])
            ->update(['status' => self::REEMPLAZADA]);

        // Los conceptos aprobados quedan facturados: una adicional posterior ya no los vuelve a incluir
        $ids = $this->items()->whereNotNull('order_item_id')->pluck('order_item_id');
        if ($ids->isNotEmpty()) {
            RepairOrderItem::whereIn('id', $ids)->update(['billed_quote_id' => $this->id]);
        }
    }

    /**
     * Huella del contenido de la cotización. Si el precio o un concepto cambia,
     * la huella cambia y la aceptación anterior deja de coincidir.
     */
    public function snapshotHash(): string
    {
        $this->loadMissing('items');

        $payload = [
            'folio'          => $this->folio,
            'version'        => $this->version,
            'tax_rate'       => round((float) $this->tax_rate, 2),
            'parts_subtotal' => round((float) $this->parts_subtotal, 2),
            'labor_subtotal' => round((float) $this->labor_subtotal, 2),
            'subtotal'       => round((float) $this->subtotal, 2),
            'tax'            => round((float) $this->tax, 2),
            'total'          => round((float) $this->total, 2),
            'valid_until'    => optional($this->valid_until)->toDateString(),
            'items'          => $this->items->map(fn ($i) => [
                'type'        => $i->type,
                'description' => $i->description,
                'quantity'    => round((float) $i->quantity, 2),
                'unit_price'  => round((float) $i->unit_price, 2),
                'subtotal'    => round((float) $i->subtotal, 2),
            ])->values()->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * El cliente acepta desde el enlace de seguimiento. Guarda evidencia de la aceptación.
     */
    public function acceptByClient(string $name, ?string $ip, ?string $agent, string $hash): void
    {
        $this->approve('enlace', $name);

        $this->update([
            'client_accepted_name'   => $name,
            'client_accepted_at'     => now(),
            'client_accepted_ip'     => $ip,
            'client_accepted_agent'  => $agent,
            'client_acceptance_hash' => $hash,
        ]);
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
