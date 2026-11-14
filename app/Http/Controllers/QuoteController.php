<?php

namespace App\Http\Controllers;

use App\Actions\Quotes\CreateQuoteAction;
use App\Models\Quote;
use App\Models\RepairOrder;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    /**
     * Crea una nueva cotización (versión) para la orden.
     */
    public function store(Request $request, RepairOrder $order, CreateQuoteAction $action)
    {
        $data = $request->validate(['notes' => 'nullable|string|max:1000']);

        $action->execute($order, $data['notes'] ?? null);

        return back()->with('success', 'Cotización generada.');
    }

    /**
     * Marca la cotización como enviada al cliente.
     */
    public function send(RepairOrder $order, Quote $quote)
    {
        abort_unless($quote->repair_order_id === $order->id, 404);

        $quote->markSent();

        return back()->with('success', 'Cotización marcada como enviada.');
    }

    /**
     * El asesor registra la aprobación y cómo la confirmó el cliente.
     */
    public function approve(Request $request, RepairOrder $order, Quote $quote)
    {
        abort_unless($quote->repair_order_id === $order->id, 404);

        if ($quote->status !== Quote::ENVIADA) {
            return back()->withErrors(['status' => 'Solo se puede aprobar una cotización enviada.']);
        }

        $data = $request->validate([
            'approval_method'  => 'required|in:' . implode(',', Quote::METHODS),
            'approved_by_name' => 'required|string|max:120',
        ]);

        $quote->approve($data['approval_method'], $data['approved_by_name']);

        return back()->with('success', 'Cotización aprobada.');
    }

    /**
     * El asesor registra el rechazo y el motivo, si lo hubo.
     */
    public function reject(Request $request, RepairOrder $order, Quote $quote)
    {
        abort_unless($quote->repair_order_id === $order->id, 404);

        if ($quote->status !== Quote::ENVIADA) {
            return back()->withErrors(['status' => 'Solo se puede rechazar una cotización enviada.']);
        }

        $data = $request->validate(['rejection_reason' => 'nullable|string|max:255']);

        $quote->reject($data['rejection_reason'] ?? null);

        return back()->with('success', 'Cotización marcada como rechazada.');
    }
}
