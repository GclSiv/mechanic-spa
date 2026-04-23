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
        $user    = auth()->user();
        $search  = $request->input('search');
        $isAdmin = $user->role === 'admin';

        // ── Quick Stats ────────────────────────────────────────────────
        $pendingStatuses = RepairOrderStatus::whereNotIn('slug', ['entregado', 'rechazado'])->pluck('id');
        $pendingQuery    = RepairOrder::whereIn('status_id', $pendingStatuses);
        if (!$isAdmin) {
            $mechanic = $user->mechanic ?? null;
            if ($mechanic) $pendingQuery->where('mechanic_id', $mechanic->id);
        }
        $ordenesActivas = $pendingQuery->count();
        $stockAlertas   = Part::whereColumn('stock', '<=', 'low_stock_threshold')->count();
        $ingresosMes    = $isAdmin
            ? Payment::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount')
            : null;

        // ── Recepciones recientes — paginado a 5, eager loading anti N+1 ──
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
            ->paginate(5)            // ← limitado a 5 por página
            ->withQueryString();

        // ── Órdenes recientes con estado y saldo — solo admin ───────────
        $recentOrders = null;
        if ($isAdmin) {
            $recentOrders = RepairOrder::with([
                'status',                        // para el badge de color
                'payments',                      // para calcular saldo
                'recepcion.client',
                'recepcion.vehicle.brand',
                'recepcion.vehicle.vehicleModel',
                'mechanic',
            ])
            ->orderByDesc('created_at')
            ->take(8)
            ->get()
            ->map(function ($order) {
                $totalPagado  = $order->payments->sum('amount');
                $saldo        = round($order->estimated_cost - $totalPagado, 2);
                return [
                    'id'           => $order->id,
                    'folio'        => $order->folio ?? 'Q-' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
                    'cliente'      => trim(($order->recepcion->client->first_name ?? '') . ' ' . ($order->recepcion->client->last_name ?? '')),
                    'vehiculo'     => trim((is_string($order->recepcion->vehicle->brand ?? null)
                        ? ($order->recepcion->vehicle->brand ?? '')
                        : ($order->recepcion->vehicle->brand->name ?? '')) . ' ' .
                        ($order->recepcion->vehicle->vehicleModel->name ?? '')),
                    'año'          => $order->recepcion->vehicle->year ?? '—',
                    'mecanico'     => $order->mechanic->name ?? 'Sin asignar',
                    'status_name'  => $order->status->name ?? '—',
                    'status_color' => $order->status->color_class ?? 'bg-gray-100 text-gray-600',
                    'total'        => round($order->estimated_cost, 2),
                    'pagado'       => round($totalPagado, 2),
                    'saldo'        => $saldo,
                    'liquidado'    => $saldo <= 0,
                ];
            });
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                'total'          => Recepcion::count(),
                'today'          => Recepcion::whereDate('created_at', now())->count(),
                'ordenesActivas' => $ordenesActivas,
                'stockAlertas'   => $stockAlertas,
                'ingresosMes'    => $ingresosMes,
            ],
            'recentRecepcions' => $recentRecepcions,
            'recentOrders'     => $recentOrders,
            'filters'          => $request->only(['search']),
        ]);
    }
}
