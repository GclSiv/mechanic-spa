<?php

namespace App\Http\Controllers;

use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\Setting;
use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class RepairOrderItemController extends Controller
{
    /**
     * Guarda un nuevo item (mano de obra/refacción) en la base de datos.
     * (Nota: Si ya usas addItem en RepairOrderController, este método es opcional)
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
        // 1. Eager load COMPLETO para que el cliente y vehículo no salgan en blanco en el PDF
        $orden = RepairOrder::with([
            'recepcion.client',
            'recepcion.vehicle.brand',
            'recepcion.vehicle.vehicleModel',
            'items'
        ])->findOrFail($id);

        // 2. Traemos la configuración (Logos, datos del taller)
        $settings = Setting::first();

        // 3. Usamos tu Calculadora Centralizada para que el PDF coincida 100% con la pantalla
        // (Respetando que las refacciones no llevan IVA y usando el % correcto)
        $breakdown = $calculator->execute($orden);

        // 4. Generamos el PDF pasando las variables que espera tu vista
        $pdf = Pdf::loadView('pdf.cotizacion', [
            'orden'     => $orden,
            'recepcion' => $orden->recepcion,
            'settings'  => $settings,
            // Extraemos los valores matemáticos exactos del Action
            'subtotal'  => $breakdown['subtotal'] ?? 0,
            'iva'       => $breakdown['tax'] ?? 0,
            'total'     => $breakdown['total'] ?? 0
        ]);

        // Ajustamos el papel a tamaño carta
        $pdf->setPaper('letter', 'portrait');
// Limpiamos el nombre de la empresa para que sea seguro en nombres de archivos (sin espacios ni caracteres raros)
        $empresaSegura = $settings->company_name ? \Illuminate\Support\Str::slug($settings->company_name) : 'Taller';

        // Generamos el PDF con el nombre dinámico y en mayúsculas
        return $pdf->stream('Cotizacion_' . strtoupper($empresaSegura) . '_' . ($orden->folio ?? $orden->id) . '.pdf');
    }
}