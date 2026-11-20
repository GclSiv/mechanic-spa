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
use Illuminate\Validation\ValidationException;

class RepairOrderController extends Controller
{
    public function addItem(Request $request, RepairOrder $order, CalculateOrderTotalsAction $calculator)
    {
        if (! $request->user()->isAdmin()) {
            // El mecánico solo registra refacciones, al precio de catálogo (nunca el que envíe el navegador).
            abort_unless($request->input('type') === 'part', 403, 'Solo puedes registrar refacciones.');
            $request->merge(['unit_price' => \App\Models\Part::find($request->input('part_id'))?->sale_price]);
        }

        $esRefaccion = $request->input('type') === 'part';

        $request->validate([
            'type'        => 'required|in:part,labor',
            'description' => 'required_if:type,labor|nullable|string',
            'part_id'     => 'required_if:type,part|nullable|exists:parts,id',
            // Refacción: piezas enteras positivas. Mano de obra: magnitud positiva con máximo dos decimales (p. ej. 1.50).
            'quantity'    => $esRefaccion
                ? 'required|integer|min:1|max:100000'
                : ['required', 'numeric', 'min:0.01', 'max:99999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'unit_price'  => 'required|numeric|min:0',
        ]);

        try {
            DB::transaction(function () use ($request, $order, $calculator) {
                $esRefaccion = $request->type === 'part';

                // Descuento atómico: la comprobación stock >= cantidad y el decremento
                // van en un solo UPDATE. Si no afecta exactamente una fila, no hay stock y se revierte todo.
                if ($esRefaccion) {
                    $descontado = \App\Models\Part::whereKey($request->part_id)
                        ->where('stock', '>=', $request->quantity)
                        ->decrement('stock', $request->quantity);

                    if ($descontado !== 1) {
                        $disponible = \App\Models\Part::find($request->part_id)?->stock ?? 0;

                        throw ValidationException::withMessages([
                            'quantity' => "Stock insuficiente. Disponible: {$disponible}",
                        ]);
                    }
                }

                $order->items()->create([
                    'type'        => $request->type,
                    'description' => $esRefaccion
                        ? \App\Models\Part::find($request->part_id)->name
                        : $request->description,
                    'part_id'     => $esRefaccion ? $request->part_id : null,
                    'quantity'    => $request->quantity,
                    'unit_price'  => $request->unit_price,
                    'subtotal'    => \App\Support\Cantidad::importe($request->quantity, $request->unit_price),
                ]);

                $calculator->execute($order);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors('Error al agregar el item.');
        }

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Concepto agregado correctamente.');
    }

    public function index()
    {
        $user     = auth()->user();
        $isAdmin  = $user->isAdmin();
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

        // ── Órdenes entregadas: solo las de las últimas 48h en el Kanban ──
        // Las más antiguas existen en BD pero no "ensucian" el tablero operativo
        $statusEntregado = RepairOrderStatus::where('slug', 'entregado')->first();
        if ($statusEntregado) {
            $query->where(function ($q) use ($statusEntregado) {
                $q->where('status_id', '!=', $statusEntregado->id)
                  ->orWhere('updated_at', '>=', now()->subHours(48));
            });
        }

        $orders = $query->get();

        if (! $isAdmin) {
            $orders->each(fn (RepairOrder $order) => $this->ocultarFinanzasParaMecanico($order));
        }

        $orders = $orders->groupBy('status_id');

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
            'items', 'status', 'mechanic', 'quotes.items',
            'followUps.mechanic',
            'payments',
            'avancePhotos',
            'recepcion.client',
            'recepcion.vehicle.brand',
            'recepcion.vehicle.vehicleModel',
        ]);

        $isAdmin = auth()->user()->isAdmin();

        if (! $isAdmin) {
            $this->ocultarFinanzasParaMecanico($order);
        }

        // Aseguramos que photos esté disponible (columna JSON en recepcions)
        $recepcion = $order->recepcion;
        if ($recepcion && !isset($recepcion->photos)) {
            $recepcion->photos = [];
        }

        return Inertia::render('RepairOrders/Show', [
            'orden'               => $order,
            'recepcion'           => $order->recepcion,
            'financial_breakdown' => $isAdmin ? $breakdown : null,
            'settings'            => \App\Models\Setting::current(),
            'statuses'            => RepairOrderStatus::orderBy('id')->get(),
            'mechanics'           => Mechanic::orderBy('name')->get(['id', 'name']),
            'parts'               => \App\Models\Part::where('stock', '>', 0)->orderBy('name')
                ->get($isAdmin
                    ? ['id', 'name', 'sale_price', 'stock', 'low_stock_threshold']
                    : ['id', 'name', 'stock', 'low_stock_threshold']),
            'isAdmin'             => $isAdmin,
        ]);
    }

    /**
     * Quita de la respuesta los datos financieros y personales que el mecánico no necesita.
     * Se aplica a la orden y a sus relaciones antes de enviarla a Inertia.
     */
    private function ocultarFinanzasParaMecanico(RepairOrder $order): void
    {
        $order->unsetRelation('payments');
        $order->unsetRelation('quotes');
        $order->makeHidden(['estimated_cost', 'tax_rate']);

        if ($order->relationLoaded('items')) {
            $order->items->each->makeHidden(['unit_price', 'subtotal']);
        }

        $order->recepcion?->client?->makeHidden(['rfc', 'phone', 'email', 'address', 'country']);
    }

    public function removeItem(RepairOrder $order, RepairOrderItem $item, CalculateOrderTotalsAction $calculator)
    {
        abort_unless(auth()->user()->isAdmin() || $item->type === 'part', 403, 'Solo puedes eliminar refacciones.');

        if ($item->repair_order_id !== $order->id) {
            abort(403, 'Item no pertenece a esta orden');
        }

        try {
            DB::transaction(function () use ($item, $order, $calculator) {
                // Devolver stock si era una refacción vinculada
                if ($item->part_id) {
                    \App\Models\Part::where('id', $item->part_id)
                        ->increment('stock', (int) $item->quantity); // refacciones: cantidad entera
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
        $lang = request()->input('lang', 'es');
        \Illuminate\Support\Facades\App::setLocale(in_array($lang, ['es','en']) ? $lang : 'es');
        // Cargamos todas las relaciones necesarias para que no salga en blanco
        $order->load([
            'items', 
            'recepcion.client', 
            'recepcion.vehicle.brand', 
            'recepcion.vehicle.vehicleModel'
        ]);

        // Traemos los ajustes del taller y calculamos los totales
        $settings = \App\Models\Setting::current();
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
    /**
     * Mecánico marca diagnóstico terminado → needs_quote + nota automática.
     */
    public function diagnosisComplete(RepairOrder $order)
    {
        $mechanic   = auth()->user()->mechanic;
        $statusDiag = \App\Models\RepairOrderStatus::where('slug', 'diagnostico')->first();

        // Solo cambiar a 'diagnostico' si la orden está en 'recibido'
        $slugsQueActualiza = ['recibido'];
        $cambiarStatus = $statusDiag
            && in_array($order->status?->slug, $slugsQueActualiza);

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $mechanic, $statusDiag, $cambiarStatus) {
            $order->update([
                'needs_quote' => true,
                'status_id'   => $cambiarStatus ? $statusDiag->id : $order->status_id,
            ]);

            $order->followUps()->create([
                'mechanic_id' => $mechanic?->id,
                'description' => '✅ Mecánico reporta diagnóstico terminado. Pendiente de envío de cotización al cliente.',
                'date'        => now()->format('Y-m-d H:i:s'),
            ]);
        });

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Diagnóstico marcado. El administrador ha sido notificado.');
    }

    /**
     * Reasignar mecánico con motivo obligatorio → guarda en bitácora.
     */
    public function reassignMechanic(Request $request, RepairOrder $order)
    {
        $request->validate([
            'mechanic_id' => 'required|exists:mechanics,id',
            'reason'      => 'required|string|min:5|max:500',
        ]);

        $previousName = $order->mechanic?->name ?? 'Sin asignar';
        $newMechanic  = \App\Models\Mechanic::findOrFail($request->mechanic_id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $request, $previousName, $newMechanic) {
            $order->update(['mechanic_id' => $request->mechanic_id]);

            $order->followUps()->create([
                'mechanic_id' => $request->mechanic_id,
                'description' => "🔄 Reasignación: {$previousName} → {$newMechanic->name}. Motivo: {$request->reason}",
                'date'        => now()->format('Y-m-d H:i:s'),
            ]);
        });

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Mecánico reasignado y motivo registrado en bitácora.');
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
    public function updateStatus(Request $request, RepairOrder $order, CalculateOrderTotalsAction $calculator)
    {
        $request->validate([
            'status_id' => 'required|exists:repair_order_statuses,id',
        ]);

        $nuevoEstado = RepairOrderStatus::findOrFail($request->status_id);

        // El mecánico solo asigna estados operativos y no saca una orden de un estado de cierre.
        // (La pertenencia de la orden ya la verificó el middleware own.order.)
        if (! $request->user()->isAdmin()) {
            abort_unless(
                in_array($nuevoEstado->slug, RepairOrderStatus::OPERATIVOS_MECANICO, true),
                403,
                'Solo puedes usar estados operativos.'
            );
            abort_if(
                in_array($order->status?->slug, RepairOrderStatus::CERRADOS, true),
                403,
                'Esta orden está cerrada. Solo administración puede cambiar su estado.'
            );
        }

        // No se entrega el vehículo con saldo pendiente
        if ($nuevoEstado->slug === 'entregado') {
            $saldo = $this->saldoPendiente($order, $calculator);
            if ($saldo > 0) {
                return back()->with('error', 'No se puede entregar el vehículo: saldo pendiente de $' . number_format($saldo, 2) . '.');
            }
        }

        // Cambiar el estado NO devuelve refacciones al inventario (decisión A1.1): eso será un módulo de movimientos.
        $order->update(['status_id' => $nuevoEstado->id]);

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Estado actualizado correctamente.');
    }

    /**
     * Saldo pendiente = total actual de la orden (fuente única: CalculateOrderTotalsAction)
     * menos los pagos registrados.
     */
    private function saldoPendiente(RepairOrder $order, CalculateOrderTotalsAction $calculator): float
    {
        $total  = (float) $calculator->execute($order)['total'];
        $pagado = (float) $order->payments()->sum('amount');

        return round(max($total - $pagado, 0), 2);
    }

    /**
     * Cambia la tasa de IVA de una orden: 16% general o 8% zona fronteriza.
     */
    public function updateTaxRate(Request $request, RepairOrder $order, CalculateOrderTotalsAction $calculator)
    {
        $data = $request->validate([
            'tax_rate' => ['required', 'numeric', \Illuminate\Validation\Rule::in([16, 8])],
        ]);

        $order->update(['tax_rate' => $data['tax_rate']]);
        $calculator->execute($order);

        return back()->with('success', 'Tasa de IVA actualizada.');
    }
}
