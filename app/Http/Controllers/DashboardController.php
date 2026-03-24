<?php

namespace App\Http\Controllers;

use App\Models\Recepcion; //  Ahora este es el jefe
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

    $recentRecepcions = Recepcion::query()
        ->with(['brand', 'vehicleModel'])
        ->when($search, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('vin_serial', 'LIKE', "%{$search}%")
                  ->orWhere('plate', 'LIKE', "%{$search}%")
                  ->orWhereHas('brand', fn($bq) => $bq->where('name', 'LIKE', "%{$search}%"))
                  ->orWhereHas('vehicleModel', fn($mq) => $mq->where('name', 'LIKE', "%{$search}%"));
            });
        })
        ->latest()
        ->paginate(10) // 👈 La paginación debe ir al final y sin ->get()
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