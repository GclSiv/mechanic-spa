<?php

namespace App\Http\Controllers;

// 1. IMPORTACIONES: Agregamos los modelos necesarios para Create/Store
use App\Models\Brand;
use App\Models\Client;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\Recepcion;

// Tus importaciones originales
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

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
        // Traemos las recepciones JUNTO con su cliente y vehículo para poder mostrarlos en la tabla
        $recepciones = Recepcion::with(['client', 'vehicle.brand', 'vehicle.vehicleModel'])
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('Recepciones/Index', [
            'recepciones' => $recepciones
        ]);
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
}