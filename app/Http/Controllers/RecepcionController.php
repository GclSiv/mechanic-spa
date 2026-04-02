<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Recepcion;
use App\Models\Client;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class RecepcionController extends Controller
{
    // ========================================================
    // Nota: Necesitas agregar este método si quieres que /recepciones funcione
    // ========================================================
    public function index()
    {
        // Eager loading obligatorio para que Vue no se quede en blanco
        $recepciones = Recepcion::with(['client', 'vehicle.brand'])->latest()->get();
        
        return Inertia::render('Recepcion/Index', [
            'recepciones' => $recepciones
        ]);
    }

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
            'photos'           => 'nullable|array',
            'photos.*'         => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $photoPaths = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $file) {
                $photoPaths[] = $file->store('recepciones', 'public');
            }
        }

        // 2. EL MOTOR DE ORQUESTACIÓN (Separación estricta de dominios)
        $recepcion = DB::transaction(function () use ($validatedData, $photoPaths) {

            // --- A) BUSCAR O CREAR AL CLIENTE ---
            $nameParts = explode(' ', $validatedData['first_name'], 2);
            $client = Client::firstOrCreate(
                ['phone' => $validatedData['phone'] ?? 'S/T-' . uniqid()],
                [
                    'first_name' => $nameParts[0],
                    'last_name'  => $nameParts[1] ?? '.',
                    'address'    => $validatedData['address'],
                    'rfc'        => $validatedData['rfc'],
                ]
            );

            // --- B) BUSCAR O CREAR EL VEHÍCULO ---
            // Aseguramos que guarde los IDs si tu tabla vehicles fue actualizada
            $vehicle = Vehicle::firstOrCreate(
                ['plate' => $validatedData['plate'] ?? 'S/P-' . strtoupper(substr(uniqid(), -6))],
                [
                    'client_id'        => $client->id,
                    'brand_id'         => $validatedData['brand_id'],
                    'vehicle_model_id' => $validatedData['vehicle_model_id'],
                    'year'             => $validatedData['year'],
                    'vin'              => $validatedData['vin_serial'],
                ]
            );

            // --- C) GUARDAR EL TICKET DE RECEPCIÓN (Solo campos transaccionales) ---
            return Recepcion::create([
                'client_id'  => $client->id,
                'vehicle_id' => $vehicle->id,
                'fuel_level' => $validatedData['fuel_level'],
                'miles'      => $validatedData['miles'],
                'symptoms'   => $validatedData['symptoms'],
                'witnesses'  => $validatedData['witnesses'] ?? [],
                'inventory'  => $validatedData['inventory'] ?? [],
                'photos'     => $photoPaths, // El modelo debería tener cast a 'array'
                'status'     => 'Pendiente'
            ]);
        });

        return redirect()->back()->with([
            'success' => '¡Vehículo y Cliente registrados con éxito!',
            'last_id' => $recepcion->id,
        ]);
    }

    // ✅ Route Model Binding Aplicado (Recepcion $recepcion)
    public function print(Recepcion $recepcion)
    {
        // Cargamos las relaciones para el PDF
        $recepcion->load(['client', 'vehicle.brand', 'vehicle.vehicleModel']);
        $settings = Setting::first();

        $pdf = Pdf::loadView('pdf.recepcion', compact('recepcion', 'settings'));
        return $pdf->stream("Nota_Recepcion_{$recepcion->id}.pdf");
    }

    public function export()
    {
        $fileName = 'ingresos_jk_automotive_' . date('Y-m-d') . '.csv';

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Folio', 'Cliente', 'Telefono', 'Marca', 'Placa', 'VIN', 'Gasolina', 'Fecha']);

            // Eager loading vital
            $recepciones = Recepcion::with(['client', 'vehicle.brand'])->orderBy('created_at', 'desc')->get();

            foreach ($recepciones as $rec) {
                fputcsv($file, [
                    '#' . $rec->id,
                    $rec->client->first_name ?? 'Desconocido',
                    $rec->client->phone ?? 'N/A',
                    $rec->vehicle->brand->name ?? 'S/M',
                    $rec->vehicle->plate ?? 'N/A',
                    $rec->vehicle->vin ?? 'N/A',
                    $rec->fuel_level,
                    $rec->created_at->format('d/m/Y H:i')
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ✅ Route Model Binding Aplicado
    public function destroy(Recepcion $recepcion)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->role !== 'admin') {
            abort(403, 'No tienes permisos para eliminar registros.');
        }

        $recepcion->delete();
        return redirect()->back()->with('success', 'Registro eliminado correctamente.');
    }

    // ✅ Route Model Binding Aplicado
    public function edit(Recepcion $recepcion)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->role !== 'admin') {
            abort(403, 'No tienes permisos para editar registros.');
        }

        // Cargar los datos del cliente y vehículo para mandarlos a la vista Edit.vue
        $recepcion->load(['client', 'vehicle']);

        return Inertia::render('Recepcion/Edit', [
            'recepcion' => $recepcion,
            'brands' => Brand::with('vehicleModels:id,brand_id,name')
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    // ✅ Route Model Binding Aplicado
    public function update(Request $request, Recepcion $recepcion)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->role !== 'admin') {
            abort(403, 'No tienes permisos para modificar registros.');
        }

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
        ]);

        DB::transaction(function () use ($validatedData, $recepcion) {
            
            // 1. Actualizar al Cliente
            if ($recepcion->client) {
                $nameParts = explode(' ', $validatedData['first_name'], 2);
                $recepcion->client->update([
                    'first_name' => $nameParts[0],
                    'last_name'  => $nameParts[1] ?? '.',
                    'phone'      => $validatedData['phone'],
                    'address'    => $validatedData['address'],
                    'rfc'        => $validatedData['rfc'],
                ]);
            }

            // 2. Actualizar el Vehículo
            if ($recepcion->vehicle) {
                $recepcion->vehicle->update([
                    'brand_id'         => $validatedData['brand_id'],
                    'vehicle_model_id' => $validatedData['vehicle_model_id'],
                    'year'             => $validatedData['year'],
                    'plate'            => $validatedData['plate'],
                    'vin'              => $validatedData['vin_serial'],
                ]);
            }

            // 3. Actualizar la Recepción (solo la data transaccional)
            $recepcion->update([
                'fuel_level' => $validatedData['fuel_level'],
                'miles'      => $validatedData['miles'],
                'symptoms'   => $validatedData['symptoms'],
            ]);
        });

        return redirect()->route('dashboard')->with('success', '¡Registro actualizado correctamente!');
    }
}