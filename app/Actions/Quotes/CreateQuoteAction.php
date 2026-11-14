<?php

namespace App\Actions\Quotes;

use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RepairOrder;
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

    public function execute(RepairOrder $order, ?string $notes = null): Quote
    {
        return DB::transaction(function () use ($order, $notes) {
            $order->load('items');

            if ($order->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'La orden no tiene conceptos para cotizar.',
                ]);
            }

            // Fija la tasa en la orden y devuelve el desglose de IVA
            $breakdown = $this->calculator->execute($order);

            $version = (int) Quote::where('repair_order_id', $order->id)->max('version') + 1;
            $base    = $order->folio ?? ('OR-' . $order->id);

            $quote = Quote::create([
                'repair_order_id' => $order->id,
                'version'         => $version,
                'folio'           => $base . '-C' . $version,
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

            foreach ($order->items as $item) {
                QuoteItem::create([
                    'quote_id'    => $quote->id,
                    'part_id'     => $item->part_id,
                    'type'        => $item->type,
                    'description' => $item->description,
                    'quantity'    => $item->quantity,
                    'unit_price'  => $item->unit_price,
                    'subtotal'    => $item->subtotal,
                ]);
            }

            return $quote;
        });
    }
}
