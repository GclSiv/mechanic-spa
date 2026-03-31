<?php

namespace App\Http\Controllers;

use App\Models\Recepcion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Despliega el Panel Principal con estadísticas y buscador.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        // 🏎️ Consulta Senior: Traemos Recepción con su Marca, Modelo y Orden de Reparación
        $recentRecepcions = Recepcion::query()
            ->with(['brand', 'vehicleModel', 'repairOrder']) 
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                      ->orWhere('vin_serial', 'LIKE', "%{$search}%")
                      ->orWhere('plate', 'LIKE', "%{$search}%");
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