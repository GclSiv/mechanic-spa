<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Recepcion;
use App\Models\Client;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // 👈 Importante para la transacción
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class RecepcionController extends Controller
{
    public function create()
    {
        return Inertia::render('Recepcion/Create', [
            'brands' => Brand::with('vehicleModels:id,brand_id,name')
                            ->select('id', 'name')
                            ->orderBy('name')
                            ->get(),
        ]);
    }

    public function store(Request $request)
    {
        // 1. Validación de los datos que envía tu formulario Vue
        $validatedData = $request->validate([
            'first_name'       => 'required|string|max:255',
            'phone'            => 'nullable|string',
            'address'          => 'nullable|string|max:500',
            'rfc'              => 'nullable|string|max:20',
            'brand_id'         => 'required|exists:brands,id',
            'vehicle_model_id' => 'required|exists:vehicle_models,id',
            'year'             => 'required|string|max:4',
            'plate'            => 'nullable|string|max:20',
            'vin_serial'       => 'nullable|string|max:50',
            'miles'            => 'nullable',
            'fuel_level'       => 'required|string',
            'symptoms'         => 'nullable|string',
            'witnesses'        => 'nullable|array',
            'inventory'        => 'nullable|array',
        ]);

        $validatedData['vehicle_model_id'] = (string) $validatedData['vehicle_model_id'];

        // 2. EL MOTOR DE ORQUESTACIÓN (Transacción Segura)
        $recepcion = DB::transaction(function () use ($validatedData) {

            // --- A) BUSCAR O CREAR AL CLIENTE ---
            // Separamos el nombre para cumplir con la tabla 'clients'
            $nameParts = explode(' ', $validatedData['first_name'], 2);
            $firstName = $nameParts[0];
            $lastName  = $nameParts[1] ?? '.'; // Evita errores si no ponen apellido

            // Buscamos por teléfono. Si no hay teléfono, creamos un ID temporal
            $phoneToSave = $validatedData['phone'] ?? 'S/T-' . uniqid();

            $client = Client::firstOrCreate(
                ['phone' => $phoneToSave],
                [
                    'first_name'       => $firstName,
                    'last_name'        => $lastName,
                    'address'          => $validatedData['address'],
                    'rfc'              => $validatedData['rfc'],
                    'client_status_id' => 1, // 1 = Activo
                ]
            );

            // --- B) BUSCAR O CREAR EL VEHÍCULO ---
            $brandName = Brand::find($validatedData['brand_id'])->name;
            $modelName = VehicleModel::find($validatedData['vehicle_model_id'])->name;
            
            // Si no hay placa, generamos una temporal para evitar el error UNIQUE de SQL
            $plateToSave = $validatedData['plate'] ?? 'S/P-' . strtoupper(substr(uniqid(), -6));

            $vehicle = Vehicle::firstOrCreate(
                ['plate' => $plateToSave],
                [
                    'client_id' => $client->id,
                    'brand'     => $brandName,
                    'model'     => $modelName,
                    'year'      => $validatedData['year'],
                    'vin'       => $validatedData['vin_serial'],
                ]
            );

            // --- C) GUARDAR EL TICKET DE RECEPCIÓN ---
            return Recepcion::create($validatedData);
        });

        // 3. Todo salió perfecto, enviamos el last_id al Modal
        return redirect()->back()->with([
            'success' => '¡Vehículo y Cliente registrados con éxito!',
            'last_id' => $recepcion->id,
        ]);
    }

    public function print($id)
    {
        $recepcion = Recepcion::with(['brand', 'vehicleModel'])->findOrFail($id);
        $settings  = Setting::first();

        $pdf = Pdf::loadView('pdf.recepcion', compact('recepcion', 'settings'));
        return $pdf->stream("Nota_Recepcion_{$recepcion->id}.pdf");
    }
}