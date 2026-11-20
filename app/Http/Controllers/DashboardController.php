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
        $isAdmin = $user->isAdmin();

        // El mecánico solo ve información de sus órdenes asignadas.
        // Sin registro de mecánico, su alcance es vacío (0), nunca "todo".
        $mechanicId = $isAdmin ? null : ($user->mechanic?->id ?? 0);

        // ── Quick Stats ────────────────────────────────────────────────
        $pendingStatuses = RepairOrderStatus::whereNotIn('slug', ['entregado', 'rechazado'])->pluck('id');
        $pendingQuery    = RepairOrder::whereIn('status_id', $pendingStatuses)
            ->when(! $isAdmin, fn ($q) => $q->where('mechanic_id', $mechanicId));
        $ordenesActivas = $pendingQuery->count();

        // Conteos globales: solo admin/owner (el mecánico no los recibe)
        $porCotizar   = $isAdmin
            ? RepairOrder::where('needs_quote', true)->where('is_paid', false)->count()
            : null;
        $stockAlertas = $isAdmin
            ? Part::whereColumn('stock', '<=', 'low_stock_threshold')->count()
            : null;

        $ingresosMes = $isAdmin
            ? (function () {
                // Pagos registrados en el mes
                $pagosRegistrados = Payment::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('amount');

                // Total de órdenes entregadas en el mes (para mostrar ingresos aunque no estén liquidadas)
                $statusEntregado = RepairOrderStatus::where('slug', 'entregado')->first();
                $ordenesEntregadas = $statusEntregado
                    ? RepairOrder::where('status_id', $statusEntregado->id)
                        ->whereMonth('updated_at', now()->month)
                        ->whereYear('updated_at', now()->year)
                        ->sum('estimated_cost')
                    : 0;

                // Mostrar el mayor de los dos valores (pagos reales vs facturado)
                return max($pagosRegistrados, $ordenesEntregadas);
            })()
            : null;

        // ── Vehículos por recoger (+48h) — regla de almacenamiento ────────
        $pendingPickupCount = 0;
        $cuentasPorCobrar   = collect();

        if ($isAdmin) {
            $statusReparado  = RepairOrderStatus::where('slug', 'reparado')->first();
            $statusEntregado = RepairOrderStatus::where('slug', 'entregado')->first();
            $repEntregadoIds = array_filter([
                $statusReparado?->id,
                $statusEntregado?->id,
            ]);

            // Órdenes reparadas pero sin entregar desde hace más de 48 horas
            if ($statusReparado) {
                $pendingPickupCount = RepairOrder::where('status_id', $statusReparado->id)
                    ->where('updated_at', '<=', now()->subHours(48))
                    ->count();
            }

            // Cuentas por cobrar: órdenes reparadas/entregadas con saldo pendiente
            if (!empty($repEntregadoIds)) {
                $cuentasPorCobrar = RepairOrder::with([
                    'payments',
                    'recepcion.client',
                    'recepcion.vehicle.brand',
                    'recepcion.vehicle.vehicleModel',
                ])
                ->whereIn('status_id', $repEntregadoIds)
                ->where('is_paid', false)
                ->orderByDesc('updated_at')
                ->get()
                ->map(function ($order) {
                    $totalPagado = $order->payments->sum('amount');
                    $saldo       = round($order->estimated_cost - $totalPagado, 2);
                    if ($saldo <= 0) return null; // ya liquidada aunque is_paid no se actualizó
                    return [
                        'id'      => $order->id,
                        'folio'   => $order->folio ?? 'Q-' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
                        'cliente' => trim(
                            ($order->recepcion->client->first_name ?? '') . ' ' .
                            ($order->recepcion->client->last_name  ?? '')
                        ),
                        'vehiculo' => trim(
                            (is_string($order->recepcion->vehicle->brand ?? null)
                                ? ($order->recepcion->vehicle->brand ?? '')
                                : ($order->recepcion->vehicle->brand->name ?? '')) . ' ' .
                            ($order->recepcion->vehicle->vehicleModel->name ?? '') . ' ' .
                            ($order->recepcion->vehicle->year ?? '')
                        ),
                        'saldo' => $saldo,
                    ];
                })
                ->filter()   // elimina los nulls
                ->values();
            }
        }

        // ── Recepciones recientes ──────────────────────────────────────
        // Admin: todas. Mecánico: solo las vinculadas a sus órdenes asignadas.
        $recepcionesVisibles = fn () => Recepcion::query()
            ->when(! $isAdmin, fn ($q) => $q->whereHas(
                'repairOrder',
                fn ($o) => $o->where('mechanic_id', $mechanicId)
            ));

        $recentRecepcions = $recepcionesVisibles()
            ->with([
                'client',
                'vehicle.brand',
                'vehicle.vehicleModel',
                'repairOrder.status',   // ← status del taller para badge en Dashboard
            ])
            // Búsqueda agrupada: el alcance del mecánico no debe mezclarse con el OR de la búsqueda
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('client', fn($c) => $c
                    ->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name',  'LIKE', "%{$search}%")
                    ->orWhere('phone',      'LIKE', "%{$search}%"))
                ->orWhereHas('vehicle', fn($v) => $v
                    ->where('plate', 'LIKE', "%{$search}%")
                    ->orWhereHas('brand', fn($b) => $b->where('name', 'LIKE', "%{$search}%"))
                    ->orWhereHas('vehicleModel', fn($m) => $m->where('name', 'LIKE', "%{$search}%")))
                ->orWhere('recepcions.id', 'LIKE', "%{$search}%")))
            ->latest()
            ->paginate(5)            // ← limitado a 5 por página
            ->withQueryString();

        // El mecánico no recibe datos de contacto ni fiscales del cliente (igual que en la orden)
        if (! $isAdmin) {
            $recentRecepcions->getCollection()->each(
                fn ($recepcion) => $recepcion->client?->makeHidden(['rfc', 'phone', 'email', 'address', 'country'])
            );
        }

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
                    'mecanico'     => $order->mechanic?->name,
                    'status_slug'  => $order->status->slug ?? null,
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
                // Admin: totales globales. Mecánico: solo recepciones de sus órdenes.
                'total'              => $recepcionesVisibles()->count(),
                'today'              => $recepcionesVisibles()->whereDate('created_at', now())->count(),
                'ordenesActivas'     => $ordenesActivas,
                'porCotizar'         => $porCotizar,
                'stockAlertas'       => $stockAlertas,
                'ingresosMes'        => $ingresosMes,
                'pendingPickupCount' => $pendingPickupCount,
            ],
            'recentRecepcions'  => $recentRecepcions,
            'filters'           => $request->only(['search']),
        ]);
    }
}
