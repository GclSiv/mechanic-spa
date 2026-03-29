<?php

namespace App\Http\Controllers;

use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\Setting;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class RepairOrderItemController extends Controller
{
    /**
     * Guarda un nuevo item (mano de obra/refacción) en la base de datos.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'repair_order_id' => 'required|exists:repair_orders,id',
            'description'     => 'required|string|max:255',
            'quantity'        => 'required|integer|min:1',
            'unit_price'      => 'required|numeric|min:0',
            'type'            => 'required|in:part,labor',
        ]);

        // Cálculo automático del subtotal antes de insertar
        $validated['subtotal'] = $validated['quantity'] * $validated['unit_price'];

        RepairOrderItem::create($validated);

        return redirect()->back()->with('success', 'Concepto añadido correctamente.');
    }

    /**
     * Genera el PDF de la cotización.
     */
    public function descargarCotizacion($id)
    {
        $orden = RepairOrder::with(['recepcion', 'items'])->findOrFail($id);
        $settings = Setting::first();

        $subtotal = (float) $orden->items->sum('subtotal');
        $iva = $subtotal * 0.16;
        $total = $subtotal + $iva;

        $pdf = Pdf::loadView('pdf.cotizacion', [
            'orden'     => $orden,
            'recepcion' => $orden->recepcion,
            'settings'  => $settings,
            'subtotal'  => $subtotal,
            'iva'       => $iva,
            'total'     => $total
        ]);

        return $pdf->stream("Cotizacion_JK_{$orden->id}.pdf");
    }
}