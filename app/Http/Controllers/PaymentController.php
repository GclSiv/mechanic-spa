<?php

namespace App\Http\Controllers;

use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Models\Payment;
use App\Models\RepairOrder;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(Request $request, RepairOrder $order, CalculateOrderTotalsAction $calculator)
    {
        $this->authorize('create', Payment::class);

        $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:Cash,Card,Transfer,Efectivo,Tarjeta,Transferencia',
            'notes'          => 'nullable|string|max:500',
            'quote_id'       => ['nullable', \Illuminate\Validation\Rule::exists('quotes', 'id')->where('repair_order_id', $order->id)],
        ]);

        $order->payments()->create($request->only('amount', 'payment_method', 'notes', 'quote_id'));

        // ── Lógica de liquidación automática ─────────────────────────────────
        // Recalcular el breakdown con el nuevo pago ya guardado
        $order->load('payments');
        $breakdown   = $calculator->execute($order);
        $totalPagado = $order->payments->sum('amount');
        $isPaid      = round($totalPagado, 2) >= round($breakdown['total'], 2);

        $order->update(['is_paid' => $isPaid]);

        return redirect()->route('repair-orders.show', $order)
            ->with('success', $isPaid
                ? 'Pago registrado. Orden completamente liquidada. ✅'
                : 'Pago registrado correctamente.'
            );
    }

    /**
     * Ticket de pago (comprobante) de un pago. Si la orden ya está liquidada, dice PAGADO.
     */
    public function ticket(Request $request, RepairOrder $order, Payment $payment, CalculateOrderTotalsAction $calculator)
    {
        abort_unless($payment->repair_order_id === $order->id, 404);
        abort_unless($request->user()?->isAdmin(), 403);

        $order->load(['payments', 'recepcion.client', 'recepcion.vehicle.brand', 'recepcion.vehicle.vehicleModel']);

        $breakdown   = $calculator->execute($order);
        $total       = round((float) $breakdown['total'], 2);
        $pagadoTotal = round((float) $order->payments->sum('amount'), 2);
        $saldo       = round(max($total - $pagadoTotal, 0), 2);

        $client  = $order->recepcion?->client;
        $vehicle = $order->recepcion?->vehicle;
        $metodos = [
            'Cash' => 'Efectivo', 'Card' => 'Tarjeta', 'Transfer' => 'Transferencia',
            'Efectivo' => 'Efectivo', 'Tarjeta' => 'Tarjeta', 'Transferencia' => 'Transferencia',
        ];

        $pdf = Pdf::loadView('pdf.ticket_pago', [
            'settings'    => Setting::current(),
            'order'       => $order,
            'payment'     => $payment,
            'total'       => $total,
            'pagadoTotal' => $pagadoTotal,
            'saldo'       => $saldo,
            'cliente'     => trim(($client?->first_name ?? '') . ' ' . ($client?->last_name ?? '')),
            'vehiculo'    => trim(($vehicle?->brand?->name ?? '') . ' ' . ($vehicle?->vehicleModel?->name ?? '')),
            'metodo'      => $metodos[$payment->payment_method] ?? $payment->payment_method,
        ])->setPaper([0, 0, 226.77, 520]); // 80 mm de ancho, tipo ticket

        return $pdf->stream('ticket-pago-' . $order->folio . '-' . $payment->id . '.pdf');
    }

    public function destroy(RepairOrder $order, Payment $payment, CalculateOrderTotalsAction $calculator)
    {
        abort_unless($payment->repair_order_id === $order->id, 404);
        $this->authorize('delete', $payment);

        $payment->delete();

        // Recalcular is_paid tras eliminar el pago
        $order->load('payments');
        $breakdown   = $calculator->execute($order);
        $totalPagado = $order->payments->sum('amount');
        $isPaid      = round($totalPagado, 2) >= round($breakdown['total'], 2);

        $order->update(['is_paid' => $isPaid]);

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Pago eliminado. Saldo actualizado.');
    }
}
