<?php

namespace App\Http\Controllers;

// 1. IMPORTACIONES: Agregamos los modelos necesarios para Create/Store
use App\Models\Brand;
use App\Models\Client;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\Recepcion;
use App\Models\Setting; 
// Tus importaciones originales
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
// 1. IMPORTA EL MODELO Y LA LIBRERÍA PDF AQUÍ ARRIBA

use Barryvdh\DomPDF\Facade\Pdf; 


class RecepcionController extends Controller
{
    /**
     * Muestra la pantalla para crear una nueva recepción.
     * (NUEVO MÉTODO)
     */
    public function create()
    {
        // 1. Mandamos las Marcas y Modelos
        $brands = Brand::with('vehicleModels')->get();
        
        // 2. Mandamos todos los Clientes JUNTO con sus Vehículos para el selector dinámico
        $clients = Client::with('vehicles')->get();

        return Inertia::render('Recepciones/Create', [
            'brands' => $brands,
            'clients' => $clients,
        ]);
    }

    /**
     * Guarda la nueva recepción en la base de datos.
     * (NUEVO MÉTODO)
     */
    /**
     * Verifica si una placa o VIN ya existe en la BD (llamada AJAX desde el formulario).
     */
    public function checkVehicle(Request $request)
    {
        $plate = trim($request->input('plate', ''));
        $vin   = trim($request->input('vin', ''));

        $result = ['plate' => null, 'vin' => null];

        if ($plate) {
            $vehicle = Vehicle::where('plate', strtoupper($plate))
                ->with(['brand', 'vehicleModel'])
                ->first();
            if ($vehicle) {
                $result['plate'] = [
                    'id'    => $vehicle->id,
                    'brand' => $vehicle->brand->name ?? $vehicle->brand ?? '—',
                    'model' => $vehicle->vehicleModel->name ?? $vehicle->model ?? '—',
                    'year'  => $vehicle->year,
                    'plate' => $vehicle->plate,
                    'vin'   => $vehicle->vin,
                ];
            }
        }

        if ($vin && strlen($vin) >= 5) {
            $vehicle = Vehicle::where('vin', strtoupper($vin))
                ->with(['brand', 'vehicleModel'])
                ->first();
            if ($vehicle) {
                $result['vin'] = [
                    'id'    => $vehicle->id,
                    'brand' => $vehicle->brand->name ?? $vehicle->brand ?? '—',
                    'model' => $vehicle->vehicleModel->name ?? $vehicle->model ?? '—',
                    'year'  => $vehicle->year,
                    'plate' => $vehicle->plate,
                    'vin'   => $vehicle->vin,
                ];
            }
        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        // 1. Validar la petición inteligentemente
        $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'first_name' => 'required_without:client_id|string|max:255',
            'last_name' => 'required_without:client_id|string|max:255',
            
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'brand_id' => 'required_without:vehicle_id|exists:brands,id',
            'vehicle_model_id' => 'required_without:vehicle_id|exists:vehicle_models,id',
            'year' => 'required_without:vehicle_id|numeric',
            
            'fuel_level' => 'required|string',
        ]);

        // 2. Lógica del CLIENTE: ¿Es nuevo o ya existe?
        if ($request->client_id) {
            $client = Client::findOrFail($request->client_id);
        } else {
            $client = Client::create($request->only(['first_name', 'last_name', 'phone', 'address', 'rfc']));
        }

        // 3. Lógica del VEHÍCULO: ¿Es un carro ya registrado o compró uno nuevo?
        if ($request->vehicle_id) {
            $vehicle = Vehicle::findOrFail($request->vehicle_id);
        } else {
            $brand = Brand::find($request->brand_id);
            $model = VehicleModel::find($request->vehicle_model_id);

            // Si ya existe un vehículo con esa placa, lo reutilizamos en lugar de crear uno nuevo
            $vehicle = Vehicle::where('plate', $request->plate)->first();

            if (!$vehicle) {
                $vehicle = Vehicle::create([
                    'client_id' => $client->id,
                    'brand_id'  => $request->brand_id,
                    'model_id'  => $request->vehicle_model_id,
                    'brand'     => $brand->name ?? 'Desconocido',
                    'model'     => $model->name ?? 'Desconocido',
                    'year'      => $request->year,
                    'plate'     => $request->plate,
                    'vin'       => $request->vin,
                    'engine'    => $request->engine,
                ]);
            }
        }

        // 4. Lógica de FOTOS (Evidencia)
        $photoPaths = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $photoPaths[] = $photo->store('recepciones', 'public');
            }
        }

        // 5. Crear la RECEPCIÓN
        $recepcion = Recepcion::create([
            'client_id'  => $client->id,
            'vehicle_id' => $vehicle->id,
            'fuel_level' => $request->fuel_level,
            'miles'      => $request->miles,
            'witnesses'  => $request->witnesses, 
            'inventory'  => $request->inventory, 
            'photos'     => !empty($photoPaths) ? $photoPaths : null,
            'symptoms'   => $request->symptoms,
            'status'     => 'Pendiente',
        ]);

        return redirect()->route('recepcion.index')->with([
            'success' => 'Recepción creada correctamente',
            'last_id' => $recepcion->id
        ]);
    }

    public function index()
    {
        $recepciones = Recepcion::with(['client', 'vehicle.brand', 'vehicle.vehicleModel'])
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('Recepciones/Index', [
            'recepciones' => $recepciones,
        ]);
    }

    /**
     * Muestra el detalle de una Recepción (recepcion.show).
     */
    public function showRecepcion(Recepcion $recepcion)
    {
        $recepcion->load(['client', 'vehicle.brand', 'vehicle.vehicleModel']);

        // Si ya tiene orden de reparación, redirigir a ella
        $orden = RepairOrder::where('recepcion_id', $recepcion->id)->first();
        if ($orden) {
            return redirect()->route('repair-orders.show', $orden->id);
        }

        // Si no, mostrar detalle básico de la recepción
        return Inertia::render('Recepciones/Show', [
            'recepcion' => $recepcion,
        ]);
    }

    /**
     * Muestra el formulario de edición de una recepción.
     */
    public function edit(Recepcion $recepcion)
    {
        $recepcion->load(['client', 'vehicle.brand', 'vehicle.vehicleModel']);
        $brands = Brand::with('vehicle_models')->get();

        return Inertia::render('Recepciones/Edit', [
            'recepcion' => $recepcion,
            'brands'    => $brands,
        ]);
    }

    /**
     * Elimina una recepción y sus datos asociados.
     */
    public function destroy(Recepcion $recepcion)
    {
        DB::transaction(function () use ($recepcion) {
            // Eliminar órdenes de reparación vinculadas
            RepairOrder::where('recepcion_id', $recepcion->id)->each(function ($order) {
                $order->items()->delete();
                $order->payments()->delete();
                $order->followUps()->delete();
                $order->delete();
            });
            $recepcion->delete();
        });

        return redirect()->route('recepcion.index')
            ->with('success', 'Recepción eliminada correctamente.');
    }

    /**
     * Actualiza una recepción existente (Conecta con el Edit.vue que hicimos)
     */
    public function update(Request $request, $id)
    {
        $recepcion = Recepcion::with(['client', 'vehicle'])->findOrFail($id);

        // 1. Actualizar Cliente
        if ($recepcion->client) {
            $recepcion->client->update($request->only(['first_name', 'last_name', 'phone', 'address', 'rfc']));
        }

        // 2. Actualizar Vehículo
        if ($recepcion->vehicle) {
            $recepcion->vehicle->update([
                'brand_id' => $request->brand_id,
                'model_id' => $request->vehicle_model_id,
                'year' => $request->year,
                'plate' => $request->plate,
                'vin' => $request->vin,
                'engine' => $request->engine,
            ]);
        }

        // 3. Actualizar Recepción (Datos Transaccionales)
        $recepcion->update($request->only(['miles', 'fuel_level', 'symptoms', 'witnesses', 'inventory']));

        return redirect()->route('recepcion.index')->with('success', 'Recepción actualizada correctamente');
    }
    /**
     * Muestra la Orden de Reparación con todos sus datos vinculados.
     * (ESTE ES TU MÉTODO ORIGINAL INTACTO)
     */
    public function show(RepairOrder $order, CalculateOrderTotalsAction $calculator)
    {
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
            'client'              => $order->recepcion->client ?? null,
            'vehicle'             => $order->recepcion->vehicle ?? null,
        ]);
    }

    /**
     * Agrega un nuevo concepto (refacción o mano de obra) a la orden.
     * (ESTE ES TU MÉTODO ORIGINAL INTACTO)
     */
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
                $calculator->execute($order);
            });
        } catch (\Throwable $e) {
            return back()->withErrors('Error al agregar el item: ' . $e->getMessage());
        }

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Concepto agregado correctamente.');
    }

    /**
     * Genera el PDF de la nota de recepción.
     */
    public function print(Recepcion $recepcion)
    {
        // 1. Eager load de todas las relaciones que necesita el blade (¡ESTO ES CORRECTO!)
        $recepcion->load([
            'client',
            'vehicle.brand',
            'vehicle.vehicleModel',
        ]);

        // 2. Configuración global del taller
        $settings = \App\Models\Setting::first(); 

        // 3. Generar PDF apuntando a la carpeta "pdf" y pasando AMBAS variables
        $pdf = Pdf::loadView('pdf.recepcion', [
            'recepcion' => $recepcion,
            'settings'  => $settings,
        ]);

        $pdf->setPaper('letter', 'portrait');

        // 4. Mostrar en el navegador
        return $pdf->stream("Recepcion-{$recepcion->id}.pdf");
    }
    public function generateOrder(Recepcion $recepcion)
    {
        try {
            // 1. Buscamos el primer registro válido que exista en tu base de datos 
            // para no forzar el "ID 1" que podría no existir.
            $mechanic = \App\Models\Mechanic::first();
            $status   = \App\Models\RepairOrderStatus::first();
            $client   = \App\Models\Client::first();
            $vehicle  = \App\Models\Vehicle::first();

            // 2. Buscamos o creamos la orden
            $order = \App\Models\RepairOrder::firstOrCreate(
                ['recepcion_id' => $recepcion->id],
                [
                    'folio'               => 'Q-' . str_pad($recepcion->id, 4, '0', STR_PAD_LEFT),
                    'client_id'           => $recepcion->client_id ?? ($client ? $client->id : null),
                    'vehicle_id'          => $recepcion->vehicle_id ?? ($vehicle ? $vehicle->id : null),
                    'mechanic_id'         => $mechanic ? $mechanic->id : null,
                    'status_id'           => $status ? $status->id : null,
                    'problem_description' => $recepcion->symptoms ?? 'Diagnóstico general',
                    'entry_date'          => now(),
                ]
            );

            // 3. Redirige exitosamente
            return redirect()->route('repair-orders.show', $order->id);

        } catch (\Exception $e) {
            // ¡EL SALVAVIDAS! Si la base de datos bloquea el guardado,
            // esto te mostrará en letras gigantes EXACTAMENTE cuál es el problema.
            dd('Error SQL al intentar crear la cotización: ' . $e->getMessage());
        }
    }
}