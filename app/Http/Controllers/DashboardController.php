<?php

namespace App\Http\Controllers;

use App\Models\Recepcion; // Usamos el modelo de las entradas
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

    // Consultamos la tabla 'clients' porque ahí es donde tienes los datos
    $recentClients = \App\Models\Client::with(['brand', 'vehicleModel'])
        ->when($search, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                // Ajustado a los nombres de tu Screenshot 309
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('vin', 'LIKE', "%{$search}%")  // En 'clients' se llama 'vin'
                  ->orWhere('plate', 'LIKE', "%{$search}%"); // Tienes columna 'plate'
            })
            // Búsqueda en relaciones
            ->orWhereHas('brand', fn($q) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orWhereHas('vehicleModel', fn($q) => $q->where('name', 'LIKE', "%{$search}%"));
        })
        ->latest()
        ->take(10)
        ->get();

    return Inertia::render('Dashboard', [
        'stats' => [
            'total' => \App\Models\Client::count(),
            'today' => \App\Models\Client::whereDate('created_at', today())->count(),
        ],
        'recentClients' => $recentClients,
        'filters' => $request->only(['search'])
    ]);
}
}