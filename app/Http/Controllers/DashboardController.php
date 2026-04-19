<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\Payment;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user   = auth()->user();
        $search = $request->input('search');
        $isAdmin = $user->role === 'admin';

        // Órdenes pendientes (no entregadas ni rechazadas)
        $pendingStatuses = RepairOrderStatus::whereNotIn('slug', ['entregado', 'rechazado'])->pluck('id');
        $pendingQuery    = RepairOrder::whereIn('status_id', $pendingStatuses);
        if (!$isAdmin) {
            $mechanic = $user->mechanic ?? null;
            if ($mechanic) $pendingQuery->where('mechanic_id', $mechanic->id);
        }
        $ordenesActivas = $pendingQuery->count();

        // Alertas de stock bajo
        $stockAlertas = Part::whereColumn('stock', '<=', 'low_stock_threshold')->count();

        // Ingresos del mes (solo admin)
        $ingresosMes = null;
        if ($isAdmin) {
            $ingresosMes = Payment::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount');
        }

        // Lista recepciones recientes con buscador
        $recentRecepcions = Recepcion::query()
            ->with(['client', 'vehicle.brand', 'vehicle.vehicleModel'])
            ->when($search, fn($q) => $q
                ->whereHas('client', fn($c) => $c
                    ->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name',  'LIKE', "%{$search}%")
                    ->orWhere('phone',      'LIKE', "%{$search}%"))
                ->orWhereHas('vehicle', fn($v) => $v
                    ->where('plate', 'LIKE', "%{$search}%")
                    ->orWhereHas('brand', fn($b) => $b->where('name', 'LIKE', "%{$search}%"))
                    ->orWhereHas('vehicleModel', fn($m) => $m->where('name', 'LIKE', "%{$search}%")))
                ->orWhere('recepcions.id', 'LIKE', "%{$search}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total'          => Recepcion::count(),
                'today'          => Recepcion::whereDate('created_at', now())->count(),
                'ordenesActivas' => $ordenesActivas,
                'stockAlertas'   => $stockAlertas,
                'ingresosMes'    => $ingresosMes,
            ],
            'recentRecepcions' => $recentRecepcions,
            'filters'          => $request->only(['search']),
        ]);
    }
}
