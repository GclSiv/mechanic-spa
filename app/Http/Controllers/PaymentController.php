<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\RepairOrder;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(Request $request, RepairOrder $order)
    {
        $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:Efectivo,Tarjeta,Transferencia',
            'notes'          => 'nullable|string|max:500',
        ]);

        $order->payments()->create($request->only('amount', 'payment_method', 'notes'));

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Pago registrado correctamente.');
    }

    public function destroy(RepairOrder $order, Payment $payment)
    {
        $payment->delete();

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Pago eliminado.');
    }
}
