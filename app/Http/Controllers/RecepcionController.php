<?php

namespace App\Http\Controllers;

// 1. IMPORTACIONES: Necesitamos avisarle a PHP dónde están estas herramientas
use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;

// 2. ESTRUCTURA: La función DEBE estar dentro de la clase
class RecepcionController extends Controller
{
    public function store(Request $request)
    {

    
        $validated = $request->validate([
            'first_name'         => 'required',
            'miles'              => 'nullable|string',
            'fuel_level'         => 'nullable|string',
            'technical_symptoms' => 'nullable|string',
            'brand_id'           => 'required|exists:brands,id',
            'vehicle_model_id'   => 'required|exists:vehicle_models,id',
            'miles'              => 'nullable|string',
            'fuel_level'         => 'nullable|string',
            'technical_symptoms' => 'nullable|string',
        ]);


        

    Client::create($validated);

    return redirect()->route('dashboard')->with('success', 'Vehículo ingresado al taller.');


        // Guardamos en la base de datos
        Client::create($validated);

        return redirect()->route('dashboard')->with('success', 'Vehículo ingresado al taller.');
    }
            public function create()
{
    return Inertia::render('Recepcion/Create', [
        // Aquí puedes pasar marcas o modelos si los necesitas en selects
        'brands' => \App\Models\Brand::all(),
    ]);
}
}