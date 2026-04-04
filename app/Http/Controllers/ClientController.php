<?php

namespace App\Http\Controllers;
use App\Models\Brand;
use App\Models\Client;
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
    $clients = \App\Models\Client::orderBy('id', 'desc')->get();

    return \Inertia\Inertia::render('Clientes/Index', [
        'clients' => $clients
    ]);
}
    public function print($id)
{
       // 1. Carga optimizada de relaciones
    $client = Client::with(['brand', 'vehicleModel'])->findOrFail($id);
    
    // 2. Obtención de la configuración global
    $settings = \App\Models\Setting::first();

    // 3. Inyección de datos en la vista Blade para PDF
    $pdf = Pdf::loadView('pdf.recepcion', [
        'client'   => $client,
        'settings' => $settings,
    ]);

    // 4. Retorno del stream (abre el PDF en el navegador)
    return $pdf->stream("Order-{$client->id}-{$client->last_name}.pdf");
  
}

}