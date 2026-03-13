<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Recepcion;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf; // 👈 Corrección de la importación
use Illuminate\Support\Facades\Redirect;

class RecepcionController extends Controller
{
    /**
     * Muestra el formulario de alta.
     */
    public function create()
    {
        return Inertia::render('Recepcion/Create', [
            'brands' => Brand::select('id', 'name')->get(),
        ]);
    }

    /**
     * Procesa y guarda la entrada técnica.
     */
    public function store(Request $request)
    {
        // 1. VALIDACIÓN: Filtro de alta precisión
        $validated = $request->validate([
            'first_name'         => 'required|string|max:255',
            'miles'              => 'nullable|string',
            'fuel_level'         => 'nullable|string',
            'technical_symptoms' => 'nullable|string',
            'brand_id'           => 'required|exists:brands,id',
            'vehicle_model_id'   => 'required|exists:vehicle_models,id',
        ]);

        // 2. PERSISTENCIA
        Recepcion::create($validated);

        // 3. RESPUESTA
        return Redirect::route('dashboard')->with('success', '¡Vehículo ingresado al taller con éxito!');
    }

    /**
     * Genera la Nota de Recepción en PDF.
     * Esta es una función INDEPENDIENTE
     */
    public function print($id)
    {
        // 1. Buscamos la recepción con sus relaciones para evitar errores
        $recepcion = Recepcion::with(['brand', 'vehicleModel'])->findOrFail($id);
        
        // 2. Traemos la configuración (Nombre del taller, dirección, RFC)
        $settings = Setting::first();

        // 3. Cargamos la vista de Blade y pasamos los datos
        // Asegúrate de que el archivo exista en resources/views/pdf/recepcion.blade.php
        $pdf = Pdf::loadView('pdf.recepcion', compact('recepcion', 'settings'));

        // 4. Retornamos el flujo del PDF para abrir en nueva pestaña
        return $pdf->stream('Nota_Recepcion_' . $recepcion->id . '.pdf');
    }
}