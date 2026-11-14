<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Support\Modules;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Panel del dueño: activa o desactiva módulos para esta instalación.
 * Solo accesible con rol owner (ver rutas).
 */
class ModuleController extends Controller
{
    public function index()
    {
        $modules = collect(Modules::all())->map(fn ($m, $key) => [
            'key'         => $key,
            'label'       => $m['label'],
            'description' => $m['description'],
            'enabled'     => Modules::enabled($key),
        ])->values();

        return Inertia::render('Owner/Modules', ['modules' => $modules]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'modules'   => 'required|array',
            'modules.*' => 'boolean',
        ]);

        foreach (array_keys(Modules::all()) as $key) {
            if (array_key_exists($key, $data['modules'])) {
                Modules::set($key, (bool) $data['modules'][$key]);
            }
        }

        return back()->with('success', 'Módulos actualizados.');
    }
}
