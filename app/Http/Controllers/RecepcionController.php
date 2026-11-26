<?php

namespace App\Http\Controllers;

use Illuminate\Validation\ValidationException;
// 1. IMPORTACIONES: Agregamos los modelos necesarios para Create/Store
use App\Models\Brand;
use App\Models\Client;
use App\Models\ClientFiscalProfile;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\VehicleSubtype;
use App\Models\VehicleType;
use App\Models\WarningLight;
use App\Models\FuelType;
use App\Models\Transmission;
use App\Models\Recepcion;
use App\Models\Setting; 
// Tus importaciones originales
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Http\Requests\StoreRecepcionRequest;
use App\Support\DiagramaDano;
use App\Http\Requests\UpdateRecepcionRequest;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
// 1. IMPORTA EL MODELO Y LA LIBRERÍA PDF AQUÍ ARRIBA

use Barryvdh\DomPDF\Facade\Pdf; 


class RecepcionController extends Controller
{
    /** RFC genéricos del SAT: no identifican a una empresa, no disparan alerta de duplicado. */
    public const RFC_GENERICOS = ['XAXX010101000', 'XEXX010101000'];

    /**
     * Muestra la pantalla para crear una nueva recepción.
     * (NUEVO MÉTODO)
     */
    public function create(Request $request)
    {
        // 1. Mandamos las Marcas y Modelos
        $brands = Brand::with('vehicleModels')->get();
        
        // 2. Mandamos todos los Clientes JUNTO con sus Vehículos para el selector dinámico
        $clients = Client::with('vehicles')->get();

        // 3. Preselección para "Nueva visita" (cliente y vehículo ya registrados)
        $preselect = [
            'client_id'  => $request->integer('client_id') ?: null,
            'vehicle_id' => $request->integer('vehicle_id') ?: null,
        ];

        return Inertia::render('Recepciones/Create', [
            'warningLights' => WarningLight::where('active', true)->orderBy('sort')->get(['code', 'name', 'icon', 'custom_icon_path']),
            'brands' => $brands,
            'clients' => $clients,
            'preselect' => $preselect,
            ...$this->catalogosVehiculo(),
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

    /**
     * Busca un cliente ya registrado con el mismo nombre y apellido (AJAX desde el formulario).
     */
    public function checkClient(Request $request)
    {
        $rfc = strtoupper(trim((string) $request->input('rfc', '')));
        if ($rfc !== '') {
            if (in_array($rfc, self::RFC_GENERICOS, true)) {
                return response()->json(['fiscal' => null]);
            }

            $perfil = ClientFiscalProfile::with('client')->where('rfc', $rfc)->first();

            return response()->json([
                'fiscal' => $perfil ? [
                    'client_id'    => $perfil->client_id,
                    'client_name'  => trim(($perfil->client->first_name ?? '') . ' ' . ($perfil->client->last_name ?? '')),
                    'razon_social' => $perfil->razon_social,
                    'rfc'          => $perfil->rfc,
                ] : null,
            ]);
        }

        $first = mb_strtolower(trim((string) $request->input('first_name', '')));
        $last  = mb_strtolower(trim((string) $request->input('last_name', '')));

        if ($first === '' || $last === '') {
            return response()->json(['client' => null]);
        }

        $client = Client::whereRaw('LOWER(first_name) = ?', [$first])
            ->whereRaw('LOWER(last_name) = ?', [$last])
            ->first();

        return response()->json([
            'client' => $client ? [
                'id'            => $client->id,
                'first_name'    => $client->first_name,
                'last_name'     => $client->last_name,
                'phone'         => $client->phone,
                'address'       => $client->address,
                'rfc'           => $client->rfc,
                'client_number' => $client->client_number,
            ] : null,
        ]);
    }

    public function store(StoreRecepcionRequest $request)
    {
        // ── Datos fiscales: un RFC identifica a una sola empresa ───────────
        $rfc          = strtoupper(trim((string) $request->rfc));
        $razonSocial  = trim((string) $request->razon_social) ?: null;
        $esGenerico   = $rfc === '' || in_array($rfc, self::RFC_GENERICOS, true);

        if (! $esGenerico) {
            $perfilExistente = ClientFiscalProfile::where('rfc', $rfc)->first();

            if ($perfilExistente) {
                if (! $request->client_id || (int) $perfilExistente->client_id !== (int) $request->client_id) {
                    throw ValidationException::withMessages([
                        'rfc' => 'Este RFC ya está registrado a otro cliente. Selecciónalo en lugar de registrarlo de nuevo.',
                    ]);
                }

                if ($razonSocial && mb_strtoupper((string) $perfilExistente->razon_social) !== mb_strtoupper($razonSocial)) {
                    throw ValidationException::withMessages([
                        'razon_social' => 'Este RFC ya tiene la razón social "' . $perfilExistente->razon_social . '". Usa esa o corrige el RFC.',
                    ]);
                }
            }
        }

        // ── Anti-duplicados: no se crea un cliente ni un vehículo que ya existe.
        // Si el cliente o el vehículo ya están, el usuario debe seleccionarlos, no volver a capturarlos.
        if (!$request->client_id) {
            $clienteExistente = Client::whereRaw('LOWER(first_name) = ?', [mb_strtolower(trim($request->first_name))])
                ->whereRaw('LOWER(last_name) = ?', [mb_strtolower(trim($request->last_name))])
                ->first();

            if ($clienteExistente) {
                throw ValidationException::withMessages([
                    'first_name' => 'Ya existe un cliente con este nombre. Selecciónalo en la lista en lugar de registrarlo de nuevo.',
                ]);
            }
        }

        if (!$request->vehicle_id) {
            if ($request->plate && Vehicle::where('plate', strtoupper(trim($request->plate)))->exists()) {
                throw ValidationException::withMessages([
                    'plate' => 'Ya existe un vehículo con esta placa. Selecciónalo en lugar de registrarlo de nuevo.',
                ]);
            }

            if ($request->vin && Vehicle::whereRaw('UPPER(vin) = ?', [strtoupper(trim($request->vin))])->exists()) {
                throw ValidationException::withMessages([
                    'vin' => 'Ya existe un vehículo con este número de serie (VIN). Selecciónalo en lugar de registrarlo de nuevo.',
                ]);
            }
        }


        // 2. Lógica del CLIENTE: ¿Es nuevo o ya existe?
        if ($request->client_id) {
            $client = Client::findOrFail($request->client_id);
            // Actualizar datos de contacto si el usuario los modificó en el formulario
            $updateData = array_filter([
                'first_name' => $request->first_name ?: null,
                'last_name'  => $request->last_name  ?: null,
                'phone'      => $request->phone      ?: null,
                'address'    => $request->address    ?: null,
                'rfc'        => $request->rfc        ?: null,
            ]);
            if (!empty($updateData)) {
                $client->update($updateData);
            }
        } else {
            $client = Client::create($request->only(['first_name', 'last_name', 'phone', 'address', 'rfc', 'country']));
        }

        // 2b. Perfil fiscal: se registra el RFC (si no es genérico) asociado al cliente
        if (! $esGenerico) {
            ClientFiscalProfile::firstOrCreate(
                ['client_id' => $client->id, 'rfc' => $rfc],
                ['razon_social' => $razonSocial ?? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''))]
            );
        }

        // 3. Lógica del VEHÍCULO: ¿Es un carro ya registrado o compró uno nuevo?
        if ($request->vehicle_id) {
            $vehicle = Vehicle::findOrFail($request->vehicle_id);
        } else {
            $brand = Brand::find($request->brand_id);
            $model = VehicleModel::find($request->vehicle_model_id);

            // Placa normalizada (vacía => null). Sin placa nunca se reutiliza un vehículo: un null no identifica a nadie.
            $placa = Vehicle::normalizarPlaca($request->plate);
            $vehicle = $placa !== null ? Vehicle::where('plate', $placa)->first() : null;

            if (!$vehicle) {
                $vehicle = Vehicle::create([
                    'client_id' => $client->id,
                    'brand_id'  => $request->brand_id,
                    'model_id'  => $request->vehicle_model_id,
                    'brand'     => $brand->name ?? 'Desconocido',
                    'model'     => $model->name ?? 'Desconocido',
                    'year'      => $request->year ?: null,
                    'plate'     => $placa,
                    'vin'       => $request->vin,
                    'identifier_type'  => $request->identifier_type,
                    'identifier_value' => $request->identifier_value,
                    'engine'    => $request->engine,
                    'color'     => $request->color,
                    'vehicle_type_id'    => $request->vehicle_type_id ?: null,
                    'vehicle_subtype_id' => $request->vehicle_subtype_id ?: null,
                    'fuel_type_id'       => $request->fuel_type_id ?: null,
                    'transmission_id'    => $request->transmission_id ?: null,
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
            'reading_type'  => $request->reading_type,
            'reading_value' => $request->reading_value,
            'tire_size'     => $request->tire_size,
            'witnesses'  => $request->witnesses,
            'damages'    => $request->damages ?: null,
            // Snapshot: se fija solo si hay daños, con el diagrama de la unidad en este momento.
            'damage_diagram' => $request->damages ? DiagramaDano::paraTipo($vehicle->vehicleType) : null,
            'photos'     => !empty($photoPaths) ? $photoPaths : null,
            'symptoms'   => $request->symptoms,
        ]);

        $this->guardarElementos($recepcion, $request->input('items', []));

        return redirect()->route('recepcion.index')->with([
            'success' => 'Recepción creada correctamente',
            'last_id' => $recepcion->id
        ]);
    }

    public function index()
    {
        // Paginación de servidor (mismo criterio que RepairOrderListController): nunca se envía el dataset completo.
        $recepciones = Recepcion::with(['client', 'vehicle.brand', 'vehicle.vehicleModel', 'repairOrder.status'])
            ->orderBy('id', 'desc')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Recepcion $recepcion) {
                $recepcion->vehicle?->withDisplay();

                return $recepcion;
            });

        return Inertia::render('Recepciones/Index', [
            'recepciones' => $recepciones,
        ]);
    }

    /**
     * Muestra el detalle de una Recepción (recepcion.show).
     */
    public function showRecepcion(Recepcion $recepcion)
    {
        $recepcion->load(['client', 'vehicle.brand', 'vehicle.vehicleModel', 'items']);

        // Si ya tiene orden de reparación, redirigir a ella
        $orden = RepairOrder::where('recepcion_id', $recepcion->id)->first();
        if ($orden) {
            return redirect()->route('repair-orders.show', $orden->id);
        }

        // Si no, mostrar detalle básico de la recepción
        $recepcion->vehicle?->withDisplay();

        return Inertia::render('Recepciones/Show', [
            'recepcion' => $recepcion,
            // Nombre de catálogo de cada testigo (también desactivados): respaldo cuando no hay clave de i18n
            'nombresTestigos' => WarningLight::pluck('name', 'code'),
        ]);
    }

    /** Catálogos de clasificación del vehículo para Crear y Editar. Solo lectura. */
    private function catalogosVehiculo(): array
    {
        return [
            'vehicleTypes'    => VehicleType::orderBy('sort')->get(['id', 'code', 'name', 'damage_diagram']),
            'vehicleSubtypes' => VehicleSubtype::orderBy('sort')->get(['id', 'vehicle_type_id', 'code', 'name']),
            'fuelTypes'       => FuelType::orderBy('sort')->get(['id', 'code', 'name']),
            'transmissions'   => Transmission::orderBy('sort')->get(['id', 'code', 'name']),
        ];
    }

    /**
     * Muestra el formulario de edición de una recepción.
     */
    public function edit(Recepcion $recepcion)
    {
        $recepcion->load(['client', 'vehicle.brand', 'vehicle.vehicleModel', 'vehicle.vehicleType', 'items']);
        $brands = Brand::with('vehicleModels')->get();

        // La relación `brand` sobrescribe la columna de texto al serializar; el texto histórico se lee crudo.
        $vehiculo = $recepcion->vehicle;
        $legacy = $vehiculo ? [
            'brand' => $vehiculo->getRawOriginal('brand'),
            'model' => $vehiculo->getRawOriginal('model'),
        ] : null;

        // Activos, más los desactivados que la recepción ya tiene guardados: nunca se pierden al editar.
        $testigos = WarningLight::where(fn ($q) => $q->where('active', true)
                ->orWhereIn('code', $recepcion->witnesses ?? []))
            ->orderBy('sort')->get(['code', 'name', 'icon', 'custom_icon_path', 'active']);

        return Inertia::render('Recepciones/Edit', [
            'recepcion'     => $recepcion,
            'warningLights' => $testigos,
            'brands'        => $brands,
            'legacyVehicle' => $legacy,
            // Diagrama con el que se muestran los daños: snapshot guardado, nunca el tipo actual si difieren.
            'damageDiagram' => DiagramaDano::deRecepcion($recepcion, $recepcion->vehicle?->vehicleType),
            ...$this->catalogosVehiculo(),
        ]);
    }

    /**
     * Elimina una recepción solo si no tiene órdenes. Una orden guarda pagos, cotizaciones
     * y seguimientos; borrarla en cascada destruiría información histórica.
     */
    public function destroy(Recepcion $recepcion)
    {
        if (RepairOrder::where('recepcion_id', $recepcion->id)->exists()) {
            return redirect()->route('recepcion.index')
                ->with('error', 'No se puede eliminar: la recepción tiene una orden con historial (pagos, cotizaciones o seguimientos).');
        }

        $recepcion->delete();

        return redirect()->route('recepcion.index')
            ->with('success', 'Recepción eliminada correctamente.');
    }

    /**
     * Guarda los elementos recibidos (Recepción V2). Descarta filas sin nombre.
     */
    private function guardarElementos(Recepcion $recepcion, array $elementos): void
    {
        $orden = 0;
        foreach (array_values($elementos) as $fila) {
            $nombre = trim((string) ($fila['name'] ?? ''));
            if ($nombre === '') {
                continue;
            }

            $recepcion->items()->create([
                'name'        => $nombre,
                'quantity'    => (int) ($fila['quantity'] ?? 1),
                'unit'        => trim((string) ($fila['unit'] ?? '')) ?: null,
                'observation' => trim((string) ($fila['observation'] ?? '')) ?: null,
                'sort_order'  => $orden++,
            ]);
        }
    }

    /**
     * Actualiza una recepción desde Editar. Solo escribe lo validado por UpdateRecepcionRequest.
     * No cambia cliente ni vehículo asignados, orden, estado, fotos ni daños.
     */
    public function update(UpdateRecepcionRequest $request, $id)
    {
        $recepcion = Recepcion::with(['client', 'vehicle'])->findOrFail($id);
        $datos = $request->validated();

        DB::transaction(function () use ($recepcion, $datos) {
            // 1. Cliente: solo campos presentes. El RFC sincroniza el perfil fiscal como en Store.
            if ($recepcion->client) {
                $cliente = Arr::only($datos, ['first_name', 'last_name', 'phone', 'address', 'rfc', 'country']);
                $rfc = null;
                if (array_key_exists('rfc', $cliente)) {
                    $rfc = strtoupper(trim((string) $cliente['rfc']));
                    $cliente['rfc'] = $rfc !== '' ? $rfc : null;
                }
                $recepcion->client->update($cliente);

                if ($rfc && ! in_array($rfc, self::RFC_GENERICOS, true)) {
                    ClientFiscalProfile::firstOrCreate(
                        ['client_id' => $recepcion->client->id, 'rfc' => $rfc],
                        ['razon_social' => trim(($recepcion->client->first_name ?? '') . ' ' . ($recepcion->client->last_name ?? ''))]
                    );
                }
            }

            // 2. Vehículo: marca/modelo solo cambian si el usuario los eligió; el texto histórico se conserva si no.
            if ($recepcion->vehicle) {
                $vehiculo = $recepcion->vehicle;
                $cambios = Arr::only($datos, ['year', 'plate', 'vin', 'identifier_type', 'identifier_value', 'engine', 'vehicle_type_id', 'vehicle_subtype_id', 'fuel_type_id', 'transmission_id']);

                // Cambiar de tipo invalida el subtipo anterior: si no se envió uno compatible, se limpia.
                if (array_key_exists('vehicle_type_id', $cambios)
                    && (int) $cambios['vehicle_type_id'] !== (int) $vehiculo->vehicle_type_id
                    && ! array_key_exists('vehicle_subtype_id', $cambios)) {
                    $cambios['vehicle_subtype_id'] = null;
                }

                if (! empty($datos['brand_id']) && (int) $datos['brand_id'] !== (int) $vehiculo->brand_id) {
                    $cambios['brand_id'] = $datos['brand_id'];
                    $cambios['brand'] = Brand::find($datos['brand_id'])?->name ?? $vehiculo->brand;
                }
                if (! empty($datos['vehicle_model_id']) && (int) $datos['vehicle_model_id'] !== (int) $vehiculo->model_id) {
                    $cambios['model_id'] = $datos['vehicle_model_id'];
                    $cambios['model'] = VehicleModel::find($datos['vehicle_model_id'])?->name ?? $vehiculo->model;
                }

                if (! empty($cambios)) {
                    $vehiculo->update($cambios);
                }
            }

            // 3. Recepción: datos de ingreso (daños y fotos no se editan aquí).
            $recepcion->update(Arr::only($datos, ['fuel_level', 'symptoms', 'witnesses', 'reading_type', 'reading_value', 'tire_size']));

            // Elementos: si el formulario los envía, la lista reemplaza la anterior (sin duplicar ni conservar filas quitadas).
            if (array_key_exists('items', $datos)) {
                $recepcion->items()->delete();
                $this->guardarElementos($recepcion, $datos['items'] ?? []);
            }
        });

        return redirect()->route('recepcion.index')->with('success', 'Recepción actualizada correctamente');
    }

    /**
     * Genera el PDF de la nota de recepción.
     */
    public function print(Recepcion $recepcion)
    {
        $lang = request()->input('lang', 'es');
        \Illuminate\Support\Facades\App::setLocale(in_array($lang, ['es','en']) ? $lang : 'es');

        // 1. Eager load de todas las relaciones que necesita el blade (¡ESTO ES CORRECTO!)
        $recepcion->load([
            'client',
            'vehicle.brand',
            'vehicle.vehicleModel',
            'items',
        ]);

        // 2. Configuración global del taller
        $settings = \App\Models\Setting::current(); 

        // 3. Generar PDF apuntando a la carpeta "pdf" y pasando AMBAS variables
        // Testigos del PDF: activos, más los desactivados que la recepción tiene guardados (histórico).
        $testigos = WarningLight::where(fn ($q) => $q->where('active', true)
                ->orWhereIn('code', $recepcion->witnesses ?? []))
            ->orderBy('sort')->get(['code', 'name', 'icon', 'custom_icon_path', 'active']);

        $pdf = Pdf::loadView('pdf.recepcion', [
            'recepcion' => $recepcion,
            'settings'  => $settings,
            'testigos'  => $testigos,
        ]);

        $pdf->setPaper('letter', 'portrait');

        // 4. Mostrar en el navegador
        return $pdf->stream("Recepcion-{$recepcion->id}.pdf");
    }
    public function generateOrder(Recepcion $recepcion, \App\Actions\RepairOrders\CreateRepairOrderAction $action)
    {
        $order = $action->execute($recepcion);

        return redirect()->route('repair-orders.show', $order->id);
    }
}