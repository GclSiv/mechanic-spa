<?php

namespace App\Http\Controllers;

use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use Illuminate\Http\Request;
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

    return back()->with([
        'success' => 'Item agregado y totales actualizados.',
        'financial_breakdown' => $breakdown
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

    return back()->with([
        'success' => 'Item eliminado y totales actualizados.',
        'financial_breakdown' => $breakdown
    ]);
}
}