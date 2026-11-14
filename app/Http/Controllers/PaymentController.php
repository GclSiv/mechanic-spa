<?php

namespace App\Http\Controllers;

use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Models\Payment;
use App\Models\RepairOrder;
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
        ]);

        $order->payments()->create($request->only('amount', 'payment_method', 'notes'));

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
