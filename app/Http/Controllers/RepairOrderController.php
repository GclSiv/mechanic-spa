<?php

namespace App\Http\Controllers;

use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class RepairOrderController extends Controller
{
    public function addItem(Request $request, RepairOrder $order, CalculateOrderTotalsAction $calculator)
    {
        $validated = $request->validate([
            'description' => 'required|string',
            'type'        => 'required|in:part,labor',
            'quantity'    => 'required|numeric|min:0.01',
            'unit_price'  => 'required|numeric|min:0',
        ]);

        $validated['subtotal'] = $validated['quantity'] * $validated['unit_price'];

        try {
            DB::transaction(function () use ($order, $validated, $calculator) {
                $order->items()->create($validated);
                return $calculator->execute($order);
            });
        } catch (\Throwable $e) {
            return back()->withErrors('Error al agregar el item.');
        }

        // ✅ Corregido el mensaje
        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Concepto agregado correctamente.');
    }

    public function show(RepairOrder $order, CalculateOrderTotalsAction $calculator)
    {
        // 1. Calculamos los totales actualizados
        $breakdown = $calculator->execute($order);

        // 2. ✅ Eager Loading Senior: Cargamos todo lo que Vue necesita para no romperse
        $order->load(['items', 'recepcion.client', 'recepcion.vehicle.brand', 'recepcion.vehicle.vehicleModel']);

        return Inertia::render('RepairOrders/Show', [
            'orden'               => $order,
            'recepcion'           => $order->recepcion, // Ya viene con cliente y vehículo
            'financial_breakdown' => $breakdown
        ]);
    }

    public function removeItem(RepairOrder $order, RepairOrderItem $item, CalculateOrderTotalsAction $calculator)
    {
        if ($item->repair_order_id !== $order->id) {
            abort(403, 'Item no pertenece a esta orden');
        }

        try {
            DB::transaction(function () use ($item, $order, $calculator) {
                $item->delete();
                return $calculator->execute($order);
            });
        } catch (\Throwable $e) {
            return back()->withErrors('Error al eliminar el item.');
        }

        // ✅ Corregido el mensaje
        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Concepto eliminado correctamente.');
    }
}