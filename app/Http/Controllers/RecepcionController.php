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
            //reglas para aceptar multiples fotografias
            'photos'           => 'nullable|array',
            'photos.*'         => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $validatedData['vehicle_model_id'] = (string) $validatedData['vehicle_model_id'];


        // NUEVO: Procesar y guardar las imágenes físicas en el servidor
        $photoPaths = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                // Guarda la foto en storage/app/public/recepciones y guarda la ruta
                $path = $photo->store('recepciones', 'public');
                $photoPaths[] = $path;
            }
        }

        // Convertimos el arreglo de rutas a formato JSON para guardarlo en la BD
        $validatedData['photos'] = json_encode($photoPaths);

        // 2. EL MOTOR DE ORQUESTACIÓN (Transacción Segura)
        $recepcion = DB::transaction(function () use ($validatedData) {

            // --- A) BUSCAR O CREAR AL CLIENTE ---
            $nameParts = explode(' ', $validatedData['first_name'], 2);
            $firstName = $nameParts[0];
            $lastName  = $nameParts[1] ?? '.';

            $phoneToSave = $validatedData['phone'] ?? 'S/T-' . uniqid();

            $client = Client::firstOrCreate(
                ['phone' => $phoneToSave],
                [
                    'first_name'       => $firstName,
                    'last_name'        => $lastName,
                    'address'          => $validatedData['address'],
                    'rfc'              => $validatedData['rfc'],
                    'client_status_id' => 1,
                ]
            );

            // --- B) BUSCAR O CREAR EL VEHÍCULO ---
            $brandName = Brand::find($validatedData['brand_id'])->name;
            $modelName = VehicleModel::find($validatedData['vehicle_model_id'])->name;
            
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

    /**
     * Exporta los registros a un archivo compatible con Excel (CSV).
     */
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

        $columns = ['Folio', 'Cliente', 'Telefono', 'Marca', 'Modelo', 'Placa', 'VIN', 'Gasolina', 'Fecha'];

        $callback = function() use($columns) {
            $file = fopen('php://output', 'w');
            fputs($file, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));
            fputcsv($file, $columns);

            $recepciones = Recepcion::with(['brand', 'vehicleModel'])->orderBy('created_at', 'desc')->get();

            foreach ($recepciones as $rec) {
                $row['Folio']    = '#' . $rec->id;
                $row['Cliente']  = $rec->first_name;
                $row['Telefono'] = $rec->phone ?? 'N/A';
                $row['Marca']    = $rec->brand ? $rec->brand->name : 'S/M';
                $row['Modelo']   = $rec->vehicleModel ? $rec->vehicleModel->name : 'S/M';
                $row['Placa']    = $rec->plate ?? 'N/A';
                $row['VIN']      = $rec->vin_serial ?? 'N/A';
                $row['Gasolina'] = $rec->fuel_level;
                $row['Fecha']    = $rec->created_at->format('d/m/Y H:i');

                fputcsv($file, array($row['Folio'], $row['Cliente'], $row['Telefono'], $row['Marca'], $row['Modelo'], $row['Placa'], $row['VIN'], $row['Gasolina'], $row['Fecha']));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

 /**
     * Elimina un registro de recepción.
     */
    public function destroy($id)
    {
        // Usamos el Facade Auth que VS Code entiende perfectamente
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Si no es admin, lo pateamos fuera con un error 403
        if ($user->role !== 'admin') {
            abort(403, 'No tienes permisos para eliminar registros.');
        }

        $recepcion = Recepcion::findOrFail($id);
        $recepcion->delete();

        return redirect()->back()->with('success', 'Registro eliminado correctamente.');
    }

    /**
     * Muestra el formulario con los datos cargados para editar.
     */
    public function edit($id)
    {
        // Usamos el Facade Auth que no marca error
        /** @var \App\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        
        if ($user->role !== 'admin') {
            abort(403, 'No tienes permisos para editar registros.');
        }

        // Buscamos la recepción que queremos editar
        $recepcion = Recepcion::findOrFail($id);

        // Renderizamos la vista Edit.vue enviando los datos y las marcas
        return Inertia::render('Recepcion/Edit', [
            'recepcion' => $recepcion,
            'brands' => Brand::with('vehicleModels:id,brand_id,name')
                            ->select('id', 'name')
                            ->orderBy('name')
                            ->get(),
        ]);
    }

    /**
     * Guarda los cambios de edición en la base de datos.
     */
    public function update(Request $request, $id)
    {
        /** @var \App\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        
        if ($user->role !== 'admin') {
            abort(403, 'No tienes permisos para modificar registros.');
        }

        // 1. Validamos los datos nuevos
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

        // 2. Buscamos y actualizamos el registro principal
        $recepcion = Recepcion::findOrFail($id);
        
        DB::transaction(function () use ($validatedData, $recepcion) {
            // Actualizamos la tabla de recepciones
            $recepcion->update($validatedData);

            // También actualizamos el cliente maestro para mantenerlo sincronizado
            $nameParts = explode(' ', $validatedData['first_name'], 2);
            $client = Client::where('phone', $recepcion->phone)->first();
            if($client) {
                $client->update([
                    'first_name' => $nameParts[0],
                    'last_name'  => $nameParts[1] ?? '.',
                    'phone'      => $validatedData['phone']
                ]);
            }
        });

        // 3. Regresamos al Dashboard con el mensaje de éxito
        return redirect()->route('dashboard')->with('success', '¡Registro actualizado correctamente!');
    }
}