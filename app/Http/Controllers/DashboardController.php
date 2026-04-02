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
        $search = $request->input('search');

        // 1. Cargamos las relaciones para que Vue no falle
        $recentRecepcions = Recepcion::query()
            ->with(['client', 'vehicle.brand', 'vehicle.vehicleModel']) // Eager loading correcto
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    
                    // A) Buscar en la tabla de Clientes (Relación 'client')
                    $q->whereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('first_name', 'LIKE', "%{$search}%")
                                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                                    ->orWhere('phone', 'LIKE', "%{$search}%"); // Extra pro: buscar por teléfono
                    })
                    
                    // B) Buscar en la tabla de Vehículos (Relación 'vehicle')
                    ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                        $vehicleQuery->where('vin', 'LIKE', "%{$search}%")
                                     ->orWhere('plate', 'LIKE', "%{$search}%")
                                     
                                     // C) Búsquedas anidadas: Buscar por nombre de la marca
                                     ->orWhereHas('brand', function ($brandQuery) use ($search) {
                                         $brandQuery->where('name', 'LIKE', "%{$search}%");
                                     })
                                     // Buscar por nombre del modelo
                                     ->orWhereHas('vehicleModel', function ($modelQuery) use ($search) {
                                         $modelQuery->where('name', 'LIKE', "%{$search}%");
                                     });
                    });

                });
            })
            ->latest()
            ->paginate(10) // La paginación debe ir al final
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