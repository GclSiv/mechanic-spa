<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\VehicleModel;
use App\Support\NombreClave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Alta de modelo dentro de una marca, desde Nueva/Editar recepción. Solo admin/owner.
 * El mismo nombre puede existir en marcas distintas; dentro de una marca no se repite.
 */
class VehicleModelController extends Controller
{
    public function store(Request $request, Brand $brand): JsonResponse
    {
        $datos = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $nombre = trim($datos['name']);

        if (VehicleModel::where('brand_id', $brand->id)->where('name_key', NombreClave::de($nombre))->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe un modelo con este nombre en esta marca.',
            ]);
        }

        $modelo = VehicleModel::create(['brand_id' => $brand->id, 'name' => $nombre]);

        return response()->json([
            'model' => ['id' => $modelo->id, 'name' => $modelo->name, 'brand_id' => $brand->id],
        ], 201);
    }
}
