<?php

namespace App\Actions\Quotes;

use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RepairOrder;
use App\Support\TaxCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Genera una nueva versión de cotización a partir de los conceptos actuales de la orden.
 * Los montos y conceptos se copian (snapshot) para que el documento no cambie después.
 */
class CreateQuoteAction
{
    public const VALID_DAYS = 15;

    public function __construct(private CalculateOrderTotalsAction $calculator) {}

    public function execute(RepairOrder $order, ?string $notes = null, string $kind = Quote::KIND_PRINCIPAL): Quote
    {
        return DB::transaction(function () use ($order, $notes, $kind) {
            $order->load('items');

            // Solo una cotización enviada y sin respuesta bloquea otra del mismo tipo
            $enviada = Quote::where('repair_order_id', $order->id)
                ->where('kind', $kind)
                ->where('status', Quote::ENVIADA)
                ->exists();

            if ($enviada) {
                throw ValidationException::withMessages([
                    'status' => 'Ya hay una cotización enviada esperando respuesta del cliente.',
                ]);
            }

            // Adicional: solo conceptos que aún no se han aprobado en otra cotización
            $items = $kind === Quote::KIND_ADICIONAL
                ? $order->items->whereNull('billed_quote_id')->values()
                : $order->items;

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => $kind === Quote::KIND_ADICIONAL
                        ? 'No hay conceptos nuevos para cotizar.'
                        : 'La orden no tiene conceptos para cotizar.',
                ]);
            }

            // Fija la tasa en la orden y devuelve el desglose de IVA
            $breakdown = $this->calculator->execute($order);

            if ($kind === Quote::KIND_ADICIONAL) {
                $breakdown = TaxCalculator::compute(
                    (float) $items->where('type', 'part')->sum('subtotal'),
                    (float) $items->where('type', 'labor')->sum('subtotal'),
                    (float) $breakdown['tax_rate'],
                );
            }

            // Un borrador anterior del mismo tipo deja de tener sentido al crear uno nuevo
            Quote::where('repair_order_id', $order->id)
                ->where('kind', $kind)
                ->where('status', Quote::BORRADOR)
                ->update(['status' => Quote::REEMPLAZADA]);

            $version = (int) Quote::where('repair_order_id', $order->id)->where('kind', $kind)->max('version') + 1;
            $base    = $order->folio ?? ('OR-' . $order->id);
            $letra   = $kind === Quote::KIND_ADICIONAL ? 'A' : 'C';

            $quote = Quote::create([
                'repair_order_id' => $order->id,
                'version'         => $version,
                'kind'            => $kind,
                'folio'           => $base . '-' . $letra . $version,
                'status'          => Quote::BORRADOR,
                'tax_rate'        => $breakdown['tax_rate'],
                'parts_subtotal'  => $breakdown['parts_subtotal'],
                'labor_subtotal'  => $breakdown['labor_subtotal'],
                'subtotal'        => $breakdown['subtotal'],
                'tax'             => $breakdown['tax'],
                'total'           => $breakdown['total'],
                'valid_until'     => now()->addDays(self::VALID_DAYS)->toDateString(),
                'notes'           => $notes,
            ]);

            foreach ($items as $item) {
                QuoteItem::create([
                    'quote_id'      => $quote->id,
                    'order_item_id' => $item->id,
                    'part_id'       => $item->part_id,
                    'type'          => $item->type,
                    'description'   => $item->description,
                    'quantity'      => $item->quantity,
                    'unit_price'    => $item->unit_price,
                    'subtotal'      => $item->subtotal,
                ]);
            }

            return $quote;
        });
    }
}
