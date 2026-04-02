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
    // Inyección de dependencias en el método
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
        $breakdown = DB::transaction(function () use ($order, $validated, $calculator) {
            $order->items()->create($validated);
            return $calculator->execute($order);
        });
    } catch (\Throwable $e) {
        return back()->withErrors('Error al agregar el item.');
    }

   return redirect()->route('repair-orders.show', $order)
    ->with('success', 'Concepto agregado correctamente.');
}
public function show(RepairOrder $order, CalculateOrderTotalsAction $calculator)
{
    // Fix #5: Eager load ANTES de calcular, e incluir todas las relaciones que Vue necesita
    $order->load([
        'items',
        'recepcion.client',
        'recepcion.vehicle.brand',
        'recepcion.vehicle.vehicleModel',
    ]);

    $breakdown = $calculator->execute($order);

    return Inertia::render('RepairOrders/Show', [
        'orden'               => $order,
        'recepcion'           => $order->recepcion,
        'financial_breakdown' => $breakdown,
    ]);
}
public function removeItem(RepairOrder $order, RepairOrderItem $item, CalculateOrderTotalsAction $calculator)
{
    if ($item->repair_order_id !== $order->id) {
        abort(403, 'Item no pertenece a esta orden');
    }

    try {
        $breakdown = DB::transaction(function () use ($item, $order, $calculator) {
            $item->delete();
            return $calculator->execute($order);
        });
    } catch (\Throwable $e) {
        return back()->withErrors('Error al eliminar el item.');
    }

    return redirect()->route('repair-orders.show', $order)
    ->with('success', 'Concepto eliminado correctamente.');
}
}