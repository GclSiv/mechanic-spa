<?php

namespace App\Http\Controllers;

use App\Actions\RepairOrders\EstadoFinancieroAction;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\RepairOrder;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(Request $request, RepairOrder $order, EstadoFinancieroAction $estado)
    {
        $this->authorize('create', Payment::class);

        $data = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:Cash,Card,Transfer,Efectivo,Tarjeta,Transferencia',
            'notes'          => 'nullable|string|max:500',
            // A2: la atribución solo puede apuntar a una cotización aprobada de esta orden
            'quote_id'       => ['nullable', Rule::exists('quotes', 'id')
                ->where('repair_order_id', $order->id)
                ->where('status', Quote::APROBADA)],
        ]);

        $monto = round((float) $data['amount'], 2);

        DB::transaction(function () use ($data, $order, $estado, $monto) {
            // Bloqueo de la orden: dos pagos simultáneos no pueden superar juntos el saldo autorizado
            RepairOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $financiero = $estado->execute($order);
            $autorizado = $financiero['total_autorizado'];

            // Sin importe autorizado se admiten anticipos (quote_id nulo). Con autorización, el total pagado
            // no puede superar lo autorizado. Los anticipos ya registrados no se tocan.
            if ($autorizado > 0 && round($financiero['total_pagado'] + $monto, 2) > $autorizado) {
                throw ValidationException::withMessages([
                    'amount' => 'El pago de $' . number_format($monto, 2) . ' excede el saldo autorizado pendiente de $' . number_format($financiero['saldo_autorizado'], 2) . '.',
                ]);
            }

            $order->payments()->create([
                'amount'         => $monto,
                'payment_method' => $data['payment_method'],
                'notes'          => $data['notes'] ?? null,
                'quote_id'       => $data['quote_id'] ?? null,
            ]);

            $estado->sincronizar($order);
        });

        $liquidado = $estado->execute($order->fresh())['liquidado'];

        return redirect()->route('repair-orders.show', $order)
            ->with('success', $liquidado
                ? 'Pago registrado. Orden completamente liquidada. ✅'
                : 'Pago registrado correctamente.'
            );
    }

    /**
     * Ticket de pago (comprobante) de un pago. Si la orden ya está liquidada, dice PAGADO.
     */
    public function ticket(Request $request, RepairOrder $order, Payment $payment, EstadoFinancieroAction $estado)
    {
        abort_unless($payment->repair_order_id === $order->id, 404);
        abort_unless($request->user()?->isAdmin(), 403);

        $order->load(['recepcion.client', 'recepcion.vehicle.brand', 'recepcion.vehicle.vehicleModel']);

        // El ticket muestra lo autorizado y su saldo, no el total técnico
        $financiero  = $estado->execute($order);
        $total       = $financiero['total_autorizado'];
        $pagadoTotal = $financiero['total_pagado'];
        $saldo       = $financiero['saldo_autorizado'];

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

    public function destroy(RepairOrder $order, Payment $payment, EstadoFinancieroAction $estado)
    {
        abort_unless($payment->repair_order_id === $order->id, 404);
        $this->authorize('delete', $payment);

        DB::transaction(function () use ($payment, $order, $estado) {
            RepairOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment->delete();
            $estado->sincronizar($order);
        });

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Pago eliminado. Saldo actualizado.');
    }
}
