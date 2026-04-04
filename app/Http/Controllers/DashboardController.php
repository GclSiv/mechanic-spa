<?php

namespace App\Http\Controllers;

use App\Models\Recepcion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Despliega el Panel Principal con estadísticas y buscador avanzado.
     */
  public function index(Request $request)
    {
        // 1. Capturamos la búsqueda del usuario
        $search = $request->input('search');

        // 2. Construimos la consulta
        $recentRecepcions = Recepcion::query()
            ->with(['client', 'vehicle.brand', 'vehicle.vehicleModel']) 
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    
                    // A) Buscar en Clientes
                    $q->whereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('first_name', 'LIKE', "%{$search}%")
                                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                                    ->orWhere('phone', 'LIKE', "%{$search}%"); 
                    })
                    // B) Buscar en Vehículos (Placas, Marca, Modelo)
                    ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                        $vehicleQuery->where('plate', 'LIKE', "%{$search}%")
                            ->orWhereHas('brand', function ($brandQuery) use ($search) {
                                $brandQuery->where('name', 'LIKE', "%{$search}%");
                            })
                            ->orWhereHas('vehicleModel', function ($modelQuery) use ($search) {
                                $modelQuery->where('name', 'LIKE', "%{$search}%");
                            });
                    })
                    // C) Buscar directamente por Folio / ID de la recepción
                    // Nota: Le agregamos "recepcions.id" para evitar ambigüedad de SQL
                    ->orWhere('recepcions.id', 'LIKE', "%{$search}%");
                });
            })
            ->latest() 
            ->paginate(10) 
            ->withQueryString(); 

        return Inertia::render('Dashboard', [
            'stats' => [
                'total' => Recepcion::count(),
                'today' => Recepcion::whereDate('created_at', now())->count(),
            ],
            'recentRecepcions' => $recentRecepcions,
            'filters' => $request->only(['search']),
        ]);
    }
}