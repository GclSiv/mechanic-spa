<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Inertia\Inertia;
// 🛠️ IMPORTANTE: Esta línea es el cable que faltaba para que funcione el Storage
use Illuminate\Support\Facades\Storage; 

class SettingController extends Controller
{
    public function edit()
    {
        return Inertia::render('Settings/Edit', [
            'settings' => Setting::first()
        ]);
    }

    public function update(Request $request)
    {
        $settings = Setting::first();

        // 1. Asignamos la validación a la variable $data
        $data = $request->validate([
            'company_name'   => 'required|string',
            'address'        => 'required|string',
            'phone'          => 'required|string',
            'license_number' => 'required|string',
            'primary_color'  => 'required|string|max:7',
            'secondary_color'=> 'required|string|max:7',
            'clauses'        => 'required|string',
            'logo'           => 'nullable|image|max:2048', // Max 2MB
        ]);

        // 2. Manejo del Logotipo (El "Turbo")
        if ($request->hasFile('logo')) {
            // Borrar logo anterior de la memoria si existe
            if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            
            // Guardamos el nuevo archivo y guardamos la ruta en $data
            $path = $request->file('logo')->store('logos', 'public');
            $data['logo_path'] = $path;
        }

        // 3. Actualización única (Un solo chispazo para todos los datos)
        $settings->update($data);

        return back()->with('message', 'Sistema JK Automotive calibrado con éxito.');
    }
     public function updateTax(\Illuminate\Http\Request $request)
    {
        $request->validate(['iva' => 'required|numeric']);
        
        $setting = \App\Models\Setting::first();
        if ($setting) {
            $setting->iva = $request->iva;
            $setting->save();
        }
        
        // Regresa a la pantalla y Laravel recalculará todos los totales automáticamente
        return back()->with('success', 'Configuración fiscal actualizada');
    }
}