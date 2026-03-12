<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Setting;
use Illuminate\Http\Request; // <-- Asegúrate de que esta línea esté aquí
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // 1. Añadimos (Request $request) para recibir los datos del buscador
    public function index(Request $request)
    {
        $search = $request->input('search');

        // Contamos cuántos clientes/vehículos hay en total (Esto se queda igual)
        $totalVehicles = Client::count();
        
        // Vehículos que entraron HOY (Esto se queda igual)
        $todayVehicles = Client::whereDate('created_at', Carbon::today())->count();

        // 2. REEMPLAZAMOS la consulta de $recentClients por esta versión con filtro
        $recentClients = Client::with(['brand', 'vehicleModel'])
            ->when($search, function($query, $search) {
                $query->where(function($q) use ($search) {
                    // Búsqueda en campos directos
                    $q->where('first_name', 'LIKE', "%$search%")
                      ->orWhere('plate', 'LIKE', "%$search%")
                      ->orWhere('vin', 'LIKE', "%$search%");
                })
                // Búsqueda en relaciones (Tablas de Marca y Modelo)
                ->orWhereHas('brand', fn($q) => $q->where('name', 'LIKE', "%$search%"))
                ->orWhereHas('vehicleModel', fn($q) => $q->where('name', 'LIKE', "%$search%"));
            })
            ->latest()
            ->take(10) // Te sugiero subirlo a 10 para que la búsqueda sea más útil
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total' => $totalVehicles,
                'today' => $todayVehicles,
            ],
            'recentClients' => $recentClients,
            // 3. PASAMOS el filtro de vuelta a Vue para que el input no se borre al escribir
            'filters' => $request->only(['search'])
        ]);
    }
}