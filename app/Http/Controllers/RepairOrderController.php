<?php

namespace App\Http\Controllers;

use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\Mechanic;
use App\Models\FollowUp;
use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class RepairOrderController extends Controller
{
    public function addItem(Request $request, RepairOrder $order, CalculateOrderTotalsAction $calculator)
    {
        $request->validate([
            'type'        => 'required|in:part,labor',
            'description' => 'required_if:type,labor|nullable|string',
            'part_id'     => 'required_if:type,part|nullable|exists:parts,id',
            'quantity'    => 'required|numeric|min:0.01',
            'unit_price'  => 'required|numeric|min:0',
        ]);

        // Si es refacción, obtener descripción y validar stock
        if ($request->type === 'part') {
            $part = \App\Models\Part::findOrFail($request->part_id);
            if ($part->stock < $request->quantity) {
                return back()->withErrors(['quantity' => "Stock insuficiente. Disponible: {$part->stock}"]);
            }
        }

        try {
            DB::transaction(function () use ($request, $order, $calculator) {
                $data = [
                    'type'        => $request->type,
                    'description' => $request->type === 'part'
                        ? \App\Models\Part::find($request->part_id)->name
                        : $request->description,
                    'part_id'     => $request->type === 'part' ? $request->part_id : null,
                    'quantity'    => $request->quantity,
                    'unit_price'  => $request->unit_price,
                    'subtotal'    => $request->quantity * $request->unit_price,
                ];

                $order->items()->create($data);

                // Descontar stock si es refacción
                if ($request->type === 'part' && $request->part_id) {
                    \App\Models\Part::where('id', $request->part_id)
                        ->decrement('stock', $request->quantity);
                }

                $calculator->execute($order);
            });
        } catch (\Throwable $e) {
            return back()->withErrors('Error al agregar el item.');
        }

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Concepto agregado correctamente.');
    }

    public function index()
    {
        $user     = auth()->user();
        $isAdmin  = $user->role === 'admin';
        $statuses = RepairOrderStatus::orderBy('id')->get();

        $query = RepairOrder::with([
            'status', 'mechanic',
            'payments',
            'recepcion.client',
            'recepcion.vehicle.brand',
            'recepcion.vehicle.vehicleModel',
        ])->orderByDesc('created_at');

        // Mecánico solo ve sus propias órdenes
        if (!$isAdmin) {
            $mechanic = $user->mechanic;
            $query->where('mechanic_id', $mechanic?->id ?? 0);
        }

        $orders = $query->get()->groupBy('status_id');

        return Inertia::render('RepairOrders/Index', [
            'statuses'       => $statuses,
            'ordersByStatus' => $orders,
            'isAdmin'        => $isAdmin,
        ]);
    }

    public function show(RepairOrder $order, CalculateOrderTotalsAction $calculator)
    {
        $breakdown = $calculator->execute($order);

        $order->load([
            'items', 'status', 'mechanic',
            'followUps.mechanic',
            'payments',
            'recepcion.client',
            'recepcion.vehicle.brand',
            'recepcion.vehicle.vehicleModel',
        ]);

        return Inertia::render('RepairOrders/Show', [
            'orden'               => $order,
            'recepcion'           => $order->recepcion,
            'financial_breakdown' => $breakdown,
            'settings'            => \App\Models\Setting::first(),
            'statuses'            => RepairOrderStatus::orderBy('id')->get(),
            'mechanics'           => Mechanic::orderBy('name')->get(['id', 'name']),
            'parts'               => \App\Models\Part::where('stock', '>', 0)->orderBy('name')->get(['id', 'name', 'sale_price', 'stock']),
            'isAdmin'             => auth()->user()->role === 'admin',
        ]);
    }

    public function removeItem(RepairOrder $order, RepairOrderItem $item, CalculateOrderTotalsAction $calculator)
    {
        if ($item->repair_order_id !== $order->id) {
            abort(403, 'Item no pertenece a esta orden');
        }

        try {
            DB::transaction(function () use ($item, $order, $calculator) {
                // Devolver stock si era una refacción vinculada
                if ($item->part_id) {
                    \App\Models\Part::where('id', $item->part_id)
                        ->increment('stock', $item->quantity);
                }
                $item->delete();
                $calculator->execute($order);
            });
        } catch (\Throwable $e) {
            return back()->withErrors('Error al eliminar el item.');
        }

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Concepto eliminado correctamente.');
    }
     /**
     * Genera y descarga el PDF de la Cotización.
     */
    public function downloadPdf(RepairOrder $order, CalculateOrderTotalsAction $calculator)
    {
        // Cargamos todas las relaciones necesarias para que no salga en blanco
        $order->load([
            'items', 
            'recepcion.client', 
            'recepcion.vehicle.brand', 
            'recepcion.vehicle.vehicleModel'
        ]);

        // Traemos los ajustes del taller y calculamos los totales
        $settings = \App\Models\Setting::first();
        $breakdown = $calculator->execute($order);

        // Generamos el PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.cotizacion', [
            'orden'     => $order,
            'recepcion' => $order->recepcion,
            'settings'  => $settings,
            'subtotal'  => $breakdown['subtotal'] ?? 0,
            'iva'       => $breakdown['tax'] ?? 0,
            'tax_rate'  => $breakdown['tax_rate'] ?? 16,
            'total'     => $breakdown['total'] ?? 0
        ]);

        // Limpiamos el nombre y descargamos
        $empresaSegura = $settings->company_name ? \Illuminate\Support\Str::slug($settings->company_name) : 'JK-Automotive';
        return $pdf->stream('Cotizacion_' . strtoupper($empresaSegura) . '_' . ($order->folio ?? $order->id) . '.pdf');
    }

    /**
     * Fase 4: Asigna un mecánico a la orden.
     */
    public function assignMechanic(Request $request, RepairOrder $order)
    {
        $request->validate([
            'mechanic_id' => 'nullable|exists:mechanics,id',
        ]);

        $order->update(['mechanic_id' => $request->mechanic_id ?? null]);

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Mecánico asignado correctamente.');
    }

    /**
     * Fase 4: Agrega una nota a la bitácora de seguimiento.
     */
    public function storeFollowUp(Request $request, RepairOrder $order)
    {
        $request->validate([
            'mechanic_id'  => 'required|exists:mechanics,id',
            'description'  => 'required|string|max:1000',
            'date'         => 'required|date',
        ]);

        $order->followUps()->create($request->only('mechanic_id', 'description', 'date'));

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Nota agregada a la bitácora.');
    }

    /**
     * Fase 3: Actualiza el estado de la orden de reparación.
     */
    public function updateStatus(Request $request, RepairOrder $order)
    {
        $request->validate([
            'status_id' => 'required|exists:repair_order_statuses,id',
        ]);

        $order->update(['status_id' => $request->status_id]);

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Estado actualizado correctamente.');
    }
}