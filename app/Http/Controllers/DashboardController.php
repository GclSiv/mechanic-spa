<?php

namespace App\Http\Controllers;

use App\Models\Recepcion; // 👈 Ahora este es el jefe
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Despliega el Panel Principal con estadísticas y buscador.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        // 1. EL CAMBIO MAESTRO: Consultamos 'Recepcion' en lugar de 'Client'
        $recentRecepcions = Recepcion::with(['brand', 'vehicleModel'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    // Ajustamos los nombres de las columnas a la tabla recepcions
                    $q->where('first_name', 'LIKE', "%{$search}%")
                      ->orWhere('vin_serial', 'LIKE', "%{$search}%") // 👈 En recepcions se llama vin_serial
                      ->orWhere('plate', 'LIKE', "%{$search}%");
                })
                // Búsqueda en relaciones (Marcas y Modelos)
                ->orWhereHas('brand', fn($q) => $q->where('name', 'LIKE', "%{$search}%"))
                ->orWhereHas('vehicleModel', fn($q) => $q->where('name', 'LIKE', "%{$search}%"));
            })
            ->latest() // Ordena por los más recientes
            ->take(10) // Trae los últimos 10
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                // 2. ESTADÍSTICAS REALES: Contamos las Recepciones
                'total' => Recepcion::count(),
                'today' => Recepcion::whereDate('created_at', Carbon::today())->count(),
            ],
            // Mantenemos el nombre 'recentClients' para que tu Dashboard.vue NO se rompa,
            // pero internamente ya está enviando la información correcta de Recepciones.
            'recentClients' => $recentRecepcions, 
            'filters' => $request->only(['search'])
        ]);
    }
}