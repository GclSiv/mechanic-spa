<?php

namespace App\Http\Controllers;

use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\Setting;
use App\Actions\RepairOrders\CalculateOrderTotalsAction; // <--- Importamos la calculadora
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class RepairOrderItemController extends Controller
{
    /**
     * Guarda un nuevo item en la base de datos.
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

        $validated['subtotal'] = $validated['quantity'] * $validated['unit_price'];

        RepairOrderItem::create($validated);

        return redirect()->back()->with('success', 'Concepto añadido correctamente.');
    }

    /**
     * Genera el PDF de la cotización.
     */
    public function descargarCotizacion($id, CalculateOrderTotalsAction $calculator)
    {
        // 1. Traemos todo (Cliente y Vehículo) para que no salga en blanco
        $orden = RepairOrder::with([
            'recepcion.client', 
            'recepcion.vehicle.brand', 
            'recepcion.vehicle.vehicleModel',
            'items'
        ])->findOrFail($id);

        $settings = Setting::first();
        
        // 2. Usamos la calculadora inteligente
        $breakdown = $calculator->execute($orden);

        // 3. Enviamos las variables al PDF
        $pdf = Pdf::loadView('pdf.cotizacion', [
            'orden'     => $orden,
            'recepcion' => $orden->recepcion,
            'client'    => $orden->recepcion->client ?? null,
            'vehicle'   => $orden->recepcion->vehicle ?? null,
            'settings'  => $settings,
            'subtotal'  => $breakdown['subtotal'],
            'iva'       => $breakdown['tax'],
            'tax_rate'  => $breakdown['tax_rate'],
            'total'     => $breakdown['total']
        ]);

        return $pdf->stream("Cotizacion_JK_{$orden->id}.pdf");
    }
}