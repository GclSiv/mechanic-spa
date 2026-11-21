<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function logo()
    {
        return response()->file(Setting::current()->logoFilePath(), [
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function edit()
    {
        return Inertia::render('Settings/Edit', [
            'settings' => Setting::current(),
        ]);
    }

    public function update(Request $request)
    {
        $settings = Setting::current();

        $data = $request->validate([
            'company_name'    => 'required|string|max:255',
            'razon_social'    => 'nullable|string|max:255',
            'rfc'             => 'nullable|string|max:13',
            'whatsapp'        => 'nullable|string|max:30',
            'website'         => 'nullable|url|max:255',
            'regimen_fiscal'       => 'nullable|string|max:150',
            'codigo_postal_fiscal' => ['nullable', 'regex:/^\d{5}$/'],
            'domicilio_fiscal'     => 'nullable|string|max:500',
            'email'           => 'nullable|email|max:255',
            'address'         => 'required|string',
            'phone'           => 'required|string',
            'license_number'  => 'required|string',
            'primary_color'   => 'required|string|max:7',
            'secondary_color' => 'required|string|max:7',
            'clauses'         => 'required|string',
            'logo'            => 'nullable|image|max:2048',
            'warranty_service_enabled' => 'boolean',
            'warranty_service_days'    => 'required|integer|min:0|max:3650',
            'warranty_service_km'      => 'nullable|integer|min:0|max:1000000',
            'warranty_parts_enabled'   => 'boolean',
            'warranty_parts_days'      => 'required|integer|min:0|max:3650',
            'warranty_notes'           => 'nullable|string|max:1000',
        ]);

        if ($request->hasFile('logo')) {
            if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $settings->update($data);

        return back()->with('message', 'Sistema JK Automotive calibrado con éxito.');
    }
}
