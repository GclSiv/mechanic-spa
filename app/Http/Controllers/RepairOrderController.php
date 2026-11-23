<?php

namespace App\Http\Controllers;

use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\Mechanic;
use App\Models\FollowUp;
use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Actions\RepairOrders\EstadoFinancieroAction;
use App\Actions\RepairOrders\EstadosFinancierosOrdenesAction;
use App\Support\ConteoOrdenes;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepairOrderController extends Controller
{
    public function addItem(Request $request, RepairOrder $order, EstadoFinancieroAction $estado)
    {
        abort_if($order->estaCerrada(), 403, 'Esta orden está cerrada. No se pueden registrar conceptos.');

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
            DB::transaction(function () use ($request, $order, $estado) {
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

                $estado->sincronizar($order);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors('Error al agregar el item.');
        }

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Concepto agregado correctamente.');
    }

    /** Estados que el selector del tablero admin ofrece: solo operativos. Cierre (entregado/rechazado) vive en Órdenes/Expediente. */
    private const ESTADOS_TABLERO_ADMIN = ['recibido', 'diagnostico', 'espera-piezas', 'en-reparacion', 'reparado'];

    public function index()
    {
        $user     = auth()->user();
        $isAdmin  = $user->isAdmin();

        // Mecánico: solo sus órdenes asignadas. Sin registro de mecánico = alcance vacío (0), nunca "todo".
        $mechanicId = $isAdmin ? null : ($user->mechanic?->id ?? 0);

        // Tablero operativo: solo estados activos. Entregadas y rechazadas no son columnas;
        // se consultan en el listado de Órdenes.
        $cerradosIds = RepairOrderStatus::whereIn('slug', RepairOrderStatus::CERRADOS)->pluck('id');

        $orders = RepairOrder::with([
            'status', 'mechanic',
            'recepcion.client',
            'recepcion.vehicle.brand',
            'recepcion.vehicle.vehicleModel',
        ])
            ->whereNotIn('status_id', $cerradosIds)
            ->when(! $isAdmin, fn ($q) => $q->where('mechanic_id', $mechanicId))
            ->orderByDesc('created_at')
            ->get();

        if ($isAdmin) {
            // Solo la condición "autorización pendiente" (sin montos): un lote desde la fuente financiera, sin N+1 ni duplicar A2.
            $estados = app(EstadosFinancierosOrdenesAction::class)->execute($orders);
            $orders->each(function (RepairOrder $order) use ($estados) {
                $order->setAttribute('pendiente_autorizacion', $estados[$order->id]['pendiente_autorizacion'] ?? 0);
            });
        } else {
            $orders->each(fn (RepairOrder $order) => $this->ocultarFinanzasParaMecanico($order));
        }

        return Inertia::render('RepairOrders/Index', [
            // Todos los estados sirven para mostrar columnas y nombres; los editables lo decide el backend por rol.
            'statuses'        => RepairOrderStatus::orderBy('id')->get(['id', 'slug', 'name']),
            'estadosEditables' => $isAdmin
                ? collect(self::ESTADOS_TABLERO_ADMIN)->values()
                : collect(RepairOrderStatus::OPERATIVOS_MECANICO)->values(),
            'ordersByStatus'  => $orders->groupBy('status_id'),
            'conteos'         => ConteoOrdenes::flujo($mechanicId),
            'isAdmin'         => $isAdmin,
        ]);
    }

    public function show(RepairOrder $order, CalculateOrderTotalsAction $calculator, EstadoFinancieroAction $estado)
    {
        $breakdown = $calculator->execute($order);

        $isAdmin = auth()->user()->isAdmin();

        $order->load([
            'items', 'status', 'mechanic', 'quotes.items',
            'followUps.mechanic',
            'payments',
            'avancePhotos',
            'recepcion.client',
            'recepcion.vehicle.brand',
            'recepcion.vehicle.vehicleModel',
        ]);

        // Derivado del backend: concepto ya facturado. La pantalla solo necesita el booleano, no el id de cotización.
        $order->items->each(function ($item) {
            $item->setAttribute('autorizado', $item->billed_quote_id !== null);
            $item->makeHidden('billed_quote_id');
        });

        if (! $isAdmin) {
            $this->ocultarFinanzasParaMecanico($order);
        }

        // Banderas de UI calculadas en backend: el frontend no decide permisos de borrado.
        $order->setAttribute('esta_cerrada', $order->estaCerrada());
        $propioMecanicoId = auth()->user()->mechanic?->id;
        $order->followUps->each(function (\App\Models\FollowUp $nota) use ($isAdmin, $order, $propioMecanicoId) {
            $nota->setAttribute('puede_eliminar', $isAdmin || (! $order->estaCerrada()
                && $propioMecanicoId !== null
                && (int) $nota->mechanic_id === (int) $propioMecanicoId));
        });

        // El mecánico ve el nombre del autor de cada nota, no su ficha (contacto, usuario, comisión).
        if (! $isAdmin) {
            $order->followUps->each(fn (\App\Models\FollowUp $nota) => $nota->mechanic?->makeHidden([
                'email', 'phone', 'commission_rate', 'user_id', 'gender_id', 'mechanic_type_id', 'is_active',
            ]));
        }

        // Estado financiero autorizado (fuente única). Solo admin/owner.
        $estadoFinanciero = $isAdmin ? $estado->execute($order) : null;

        // Aseguramos que photos esté disponible (columna JSON en recepcions)
        $recepcion = $order->recepcion;
        if ($recepcion && !isset($recepcion->photos)) {
            $recepcion->photos = [];
        }

        return Inertia::render('RepairOrders/Show', [
            'orden'               => $order,
            'recepcion'           => $order->recepcion,
            'financial_breakdown' => $isAdmin ? $breakdown : null,
            'estado_financiero'   => $estadoFinanciero,
            'settings'            => \App\Models\Setting::current()->brandingData(),
            'statuses'            => RepairOrderStatus::orderBy('id')->get(),
            'mechanics'           => $isAdmin ? Mechanic::orderBy('name')->get(['id', 'name']) : [],
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
        $order->makeHidden(['estimated_cost', 'tax_rate', 'is_paid']);
        $order->mechanic?->makeHidden(['email', 'phone', 'commission_rate', 'user_id', 'gender_id', 'mechanic_type_id', 'is_active']);

        if ($order->relationLoaded('items')) {
            $order->items->each->makeHidden(['unit_price', 'subtotal']);
        }

        $order->recepcion?->client?->makeHidden(['rfc', 'phone', 'email', 'address', 'country']);
    }

    public function removeItem(RepairOrder $order, RepairOrderItem $item, EstadoFinancieroAction $estado)
    {
        abort_unless(auth()->user()->isAdmin() || $item->type === 'part', 403, 'Solo puedes eliminar refacciones.');
        abort_if($order->estaCerrada(), 403, 'Esta orden está cerrada. No se pueden eliminar conceptos.');

        if ($item->repair_order_id !== $order->id) {
            abort(403, 'Item no pertenece a esta orden');
        }

        // A2: un concepto ya autorizado en una cotización aprobada no se elimina
        if ($item->billed_quote_id !== null) {
            throw ValidationException::withMessages([
                'item' => 'Este concepto ya está autorizado en una cotización y no puede eliminarse.',
            ]);
        }

        try {
            DB::transaction(function () use ($item, $order, $estado) {
                // Devolver stock si era una refacción vinculada
                if ($item->part_id) {
                    \App\Models\Part::where('id', $item->part_id)
                        ->increment('stock', (int) $item->quantity); // refacciones: cantidad entera
                }
                $item->delete();
                $estado->sincronizar($order);
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
        $empresaSegura = $settings->company_name ? \Illuminate\Support\Str::slug($settings->company_name) : 'taller';
        return $pdf->stream('Cotizacion_' . strtoupper($empresaSegura) . '_' . ($order->folio ?? $order->id) . '.pdf');
    }

    /**
    /**
     * Mecánico marca diagnóstico terminado → needs_quote + nota automática.
     */
    public function diagnosisComplete(RepairOrder $order)
    {
        abort_if($order->estaCerrada(), 403, 'Esta orden está cerrada. No se puede registrar el diagnóstico.');

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
        abort_if($order->estaCerrada(), 403, 'Esta orden está cerrada. No se puede cambiar el mecánico.');

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
        abort_if($order->estaCerrada(), 403, 'Esta orden está cerrada. No se puede cambiar el mecánico.');

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
        abort_if($order->estaCerrada(), 403, 'Esta orden está cerrada. No se pueden agregar notas.');

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
    public function updateStatus(Request $request, RepairOrder $order, EstadoFinancieroAction $estado)
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

        // A2: entregar solo con saldo autorizado en 0 y sin trabajo pendiente de autorizar
        if ($nuevoEstado->slug === 'entregado') {
            $financiero = $estado->execute($order);

            if ($financiero['pendiente_autorizacion'] > 0) {
                return back()->with('error', 'No se puede entregar el vehículo: hay trabajo pendiente de autorización por $' . number_format($financiero['pendiente_autorizacion'], 2) . '.');
            }
            if ($financiero['saldo_autorizado'] > 0) {
                return back()->with('error', 'No se puede entregar el vehículo: saldo autorizado pendiente de $' . number_format($financiero['saldo_autorizado'], 2) . '.');
            }
        }

        // Cambiar el estado NO devuelve refacciones al inventario (decisión A1.1): eso será un módulo de movimientos.
        $order->update(['status_id' => $nuevoEstado->id]);

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Estado actualizado correctamente.');
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
