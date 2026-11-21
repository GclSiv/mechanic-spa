<?php

namespace App\Http\Controllers;
use App\Models\Brand;
use App\Actions\RepairOrders\EstadosFinancierosOrdenesAction;
use App\Models\Client;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;


class ClientController extends Controller
{
    
public function create()
{
   // 2. Pedimos TODAS las marcas CON sus respectivos modelos
$brands = Brand::with('vehicleModels')->get();

    
    // 3. Enviamos la vista y le pasamos la variable $brands
    return Inertia::render('Clients/Create', [
        'brands' => $brands
    ]);
     
}
// Solo indicamos la carpeta y el nombre del archivo
   
   /* protected $fillable = [
    'first_name',
    'last_name',
    'business_name',
    'address',
    'phone',
    'email',
    'rfc'
    ];
    */

public function store(Request $request)
{
    // 1. Validamos los datos (es como el control de calidad)
    $validated = $request->validate([
        'first_name' => 'required|string|max:255',
        'phone'      => 'required|string|max:20',
        'address'    => 'nullable|string',
        'rfc'        => 'nullable|string',
        'brand_id'      => 'required|exists:brands,id',
        'vehicle_model_id'      => 'required|exists:vehicle_models,id',
        'year'       => 'required|integer',
        'plate'      => 'required|string',
        'engine'     => 'nullable|string',
        'vin'        => 'nullable|string',
        'miles'      => 'nullable|string',
        'description'=> 'nullable|string',
        
    ]);

    // 2. Guardamos en la base de datos
    Client::create([
        'first_name'        => $validated['first_name'],
        'phone'             => $validated['phone'],
        'address'           => $validated['address'],
        'rfc'               => $validated['rfc'],
        'brand_id'          => $validated['brand_id'], // Laravel usa brand_id
        'vehicle_model_id'  => $validated['vehicle_model_id'], // Laravel usa vehicle_model_id
        'year'              => $validated['year'],
        'plate'             => $validated['plate'],
        'engine'            => $validated['engine'],
        'vin'               => $validated['vin'],
        'miles'             => $validated['miles'],
        'description'       => $validated['description'],
        'client_status_id'  => 1, // Estatus inicial (Activo)
    ]);


    // 3. Regresamos con un mensaje de éxito
    return redirect()->back()->with('success', '¡Recepción de vehículo registrada exitosamente!');
}
public function index()
{
    // Traemos todos los clientes ordenados por el más reciente
    $clients = \App\Models\Client::with(['vehicles.brand', 'vehicles.vehicleModel'])->orderBy('id', 'desc')->get();

    return Inertia::render('Clients/Index', [
        'clients' => $clients
    ]);
}
    /**
     * Expediente del cliente (B1.1): encabezado, resumen y vehículos.
     * Solo admin/owner (ClientPolicy). El resumen financiero (B1.2.2) se calcula en lote con EstadosFinancierosOrdenesAction;
     * no carga cotizaciones, pagos ni historial completo en la vista.
     */
    public function show(Client $client, EstadosFinancierosOrdenesAction $lector)
    {
        $this->authorize('view', $client);

        $client->load('fiscalProfiles');
        $client->loadCount(['vehicles', 'repairOrders']);
        $client->loadMax('recepciones', 'created_at');

        // Vehículos del cliente con conteo y última visita en una sola consulta (sin N+1)
        $vehiculos = $client->vehicles()
            ->with(['brand', 'vehicleModel'])
            ->withCount('recepciones')
            ->withMax('recepciones', 'created_at')
            ->orderBy('id')
            ->get();

        // Kilometraje de la última visita por vehículo. La visita más reciente se define por created_at
        // (único campo temporal de recepcions); el id solo desempata. Una sola consulta, sin N+1.
        $kilometrajes = Recepcion::query()
            ->from('recepcions as r')
            ->where('r.client_id', $client->id)
            ->whereRaw('r.id = (
                SELECT r2.id FROM recepcions r2
                WHERE r2.vehicle_id = r.vehicle_id AND r2.client_id = r.client_id
                ORDER BY r2.created_at DESC, r2.id DESC
                LIMIT 1
            )')
            ->get(['r.vehicle_id', 'r.miles'])
            ->pluck('miles', 'vehicle_id');

        // Resumen financiero del cliente: suma de los estados por orden que calcula el lector en lote.
        // Cada cifra es SUMA por orden; nunca se netea entre órdenes ni se deriva el saldo del total.
        $estados = $lector->execute($client->repairOrders()->pluck('id')->all());
        $resumenFinanciero = [
            'total_autorizado'       => $this->sumarEstados($estados, 'total_autorizado'),
            'total_pagado'           => $this->sumarEstados($estados, 'total_pagado'),
            'saldo_autorizado'       => $this->sumarEstados($estados, 'saldo_autorizado'),
            'pendiente_autorizacion' => $this->sumarEstados($estados, 'pendiente_autorizacion'),
            'excedente_registrado'   => $this->sumarEstados($estados, 'sobrepago'),
        ];

        $fmt = fn ($fecha) => $fecha ? Carbon::parse($fecha)->format('d/m/Y') : null;

        $servicios = $this->serviciosDelCliente($client, $lector);

        return Inertia::render('Clients/Show', [
            'client' => [
                'id'            => $client->id,
                'client_number' => $client->client_number,
                'first_name'    => $client->first_name,
                'last_name'     => $client->last_name,
                'phone'         => $client->phone,
                'email'         => $client->email,
                'address'       => $client->address,
                'rfc'           => $client->rfc,
                'country'       => $client->country,
                'fiscal_profiles' => $client->fiscalProfiles->map(fn ($f) => [
                    'id'           => $f->id,
                    'rfc'          => $f->rfc,
                    'razon_social' => $f->razon_social,
                ])->values(),
            ],
            'resumen' => [
                'vehiculos'     => $client->vehicles_count,
                'ordenes'       => $client->repair_orders_count,
                'ultima_visita' => $fmt($client->recepciones_max_created_at),
            ],
            'resumen_financiero' => $resumenFinanciero,
            'servicios' => $servicios,
            'vehiculos' => $vehiculos->map(fn ($v) => [
                'id'          => $v->id,
                'marca'       => $v->brand?->name ?? $v->getRawOriginal('brand'),
                'modelo'      => $v->vehicleModel?->name ?? $v->getRawOriginal('model'),
                'year'        => $v->year,
                'plate'       => $v->plate,
                'vin'         => $v->vin,
                'color'       => $v->color,
                'visitas'     => $v->recepciones_count,
                'ultima_visita' => $fmt($v->recepciones_max_created_at),
                'kilometraje' => $kilometrajes[$v->id] ?? null,
            ])->values(),
        ]);
    }

    /**
     * Servicios del cliente (B1.2.3): órdenes del cliente, más recientes primero, 15 por página.
     * El estado financiero de la página se calcula en lote con EstadosFinancierosOrdenesAction.
     * Orden: created_at DESC y id DESC como desempate. entry_date se muestra como fecha del servicio.
     */
    private function serviciosDelCliente(Client $client, EstadosFinancierosOrdenesAction $lector): LengthAwarePaginator
    {
        $pagina = $client->repairOrders()
            ->with(['status', 'mechanic', 'vehicle.brand', 'vehicle.vehicleModel'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Una sola llamada en lote para las órdenes de la página (se pasan instancias: no se releen)
        $estados = $lector->execute($pagina->getCollection()->all());

        return $pagina->through(function (RepairOrder $o) use ($estados) {
            $f = $estados[$o->id] ?? null;
            $v = $o->vehicle;

            return [
                'id'       => $o->id,
                'folio'    => $o->folio,
                'fecha'    => Carbon::parse($o->entry_date ?? $o->created_at)->locale('es')->isoFormat('D MMM YYYY'),
                'estado'   => $o->status ? ['slug' => $o->status->slug, 'color_class' => $o->status->color_class] : null,
                'vehiculo' => $v ? [
                    'marca'  => $v->brand?->name ?? $v->getRawOriginal('brand'),
                    'modelo' => $v->vehicleModel?->name ?? $v->getRawOriginal('model'),
                    'year'   => $v->year,
                    'plate'  => $v->plate,
                ] : null,
                'mecanico'   => $o->mechanic?->name,
                'financiero' => [
                    'total_tecnico'          => $f['total_tecnico'] ?? 0.0,
                    'total_autorizado'       => $f['total_autorizado'] ?? 0.0,
                    'total_pagado'           => $f['total_pagado'] ?? 0.0,
                    'saldo_autorizado'       => $f['saldo_autorizado'] ?? 0.0,
                    'pendiente_autorizacion' => $f['pendiente_autorizacion'] ?? 0.0,
                ],
            ];
        });
    }

    /** Suma un campo numérico de los estados por orden. Solo suma; no recalcula reglas financieras. */
    private function sumarEstados(array $estados, string $campo): float
    {
        return round(array_sum(array_column($estados, $campo)), 2);
    }

    public function print($id)
{
       // 1. Carga optimizada de relaciones
    $client = Client::with(['brand', 'vehicleModel'])->findOrFail($id);
    
    // 2. Obtención de la configuración global
    $settings = \App\Models\Setting::current();

    // 3. Inyección de datos en la vista Blade para PDF
    $pdf = Pdf::loadView('pdf.recepcion', [
        'client'   => $client,
        'settings' => $settings,
    ]);

    // 4. Retorno del stream (abre el PDF en el navegador)
    return $pdf->stream("Order-{$client->id}-{$client->last_name}.pdf");
  
}

}