<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Support\NombreClave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Alta de marca desde Nueva/Editar recepción. Solo admin/owner (la ruta usa role:admin; el mecánico recibe 403).
 * No hay pantalla de administración de catálogo todavía.
 */
class BrandController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $nombre = trim($datos['name']);

        if (Brand::where('name_key', NombreClave::de($nombre))->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe una marca con este nombre.',
            ]);
        }

        $marca = Brand::create(['name' => $nombre]);

        return response()->json([
            'brand' => ['id' => $marca->id, 'name' => $marca->name, 'vehicle_models' => []],
        ], 201);
    }
}
