<?php

namespace App\Http\Controllers;

use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Models\Payment;
use App\Models\RepairOrder;
use Inertia\Inertia;

class FinanceController extends Controller
{
    public function index(CalculateOrderTotalsAction $calculator)
    {
        // ── Cuentas por cobrar ────────────────────────────────────────────
        // Cualquier orden con saldo pendiente, excepto las rechazadas.
        // Se muestra el estado para saber si aún está en proceso o ya se entregó.
        $cuentasPorCobrar = RepairOrder::with([
                'payments',
                'status',
                'recepcion.client',
                'recepcion.vehicle.brand',
                'recepcion.vehicle.vehicleModel',
            ])
            ->whereHas('status', fn ($q) => $q->where('slug', '!=', 'rechazado'))
            ->where('estimated_cost', '>', 0)
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($order) {
                $totalPagado = $order->payments->sum('amount');
                $saldo       = round($order->estimated_cost - $totalPagado, 2);
                if ($saldo <= 0) return null;
                return [
                    'id'          => $order->id,
                    'folio'       => $order->folio ?? 'Q-' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
                    'cliente'     => trim(
                        ($order->recepcion->client->first_name ?? '') . ' ' .
                        ($order->recepcion->client->last_name  ?? '')
                    ),
                    'vehiculo'    => trim(
                        (is_string($order->recepcion->vehicle->brand ?? null)
                            ? ($order->recepcion->vehicle->brand ?? '')
                            : ($order->recepcion->vehicle->brand->name ?? '')) . ' ' .
                        ($order->recepcion->vehicle->vehicleModel->name ?? '') . ' ' .
                        ($order->recepcion->vehicle->year ?? '')
                    ),
                    'status_name'  => $order->status->name  ?? '—',
                    'status_color' => $order->status->color_class ?? 'bg-gray-100 text-gray-600',
                    'total'       => round($order->estimated_cost, 2),
                    'pagado'      => round($totalPagado, 2),
                    'saldo'       => $saldo,
                    'updated_at'  => $order->updated_at?->format('d/m/Y'),
                ];
            })
            ->filter()
            ->values();

        // ── Órdenes recientes (últimas 15) con saldo ─────────────────────
        $recentOrders = RepairOrder::with([
            'status', 'payments', 'mechanic',
            'recepcion.client',
            'recepcion.vehicle.brand',
            'recepcion.vehicle.vehicleModel',
        ])
        ->orderByDesc('created_at')
        ->take(15)
        ->get()
        ->map(function ($order) {
            $totalPagado = $order->payments->sum('amount');
            $saldo       = round($order->estimated_cost - $totalPagado, 2);
            return [
                'id'           => $order->id,
                'folio'        => $order->folio ?? 'Q-' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
                'cliente'      => trim(
                    ($order->recepcion->client->first_name ?? '') . ' ' .
                    ($order->recepcion->client->last_name  ?? '')
                ),
                'vehiculo'     => trim(
                    (is_string($order->recepcion->vehicle->brand ?? null)
                        ? ($order->recepcion->vehicle->brand ?? '')
                        : ($order->recepcion->vehicle->brand->name ?? '')) . ' ' .
                    ($order->recepcion->vehicle->vehicleModel->name ?? '') . ' ' .
                    ($order->recepcion->vehicle->year ?? '')
                ),
                'mecanico'     => $order->mechanic->name ?? null,
                'status_name'  => $order->status->name  ?? '—',
                'status_color' => $order->status->color_class ?? 'bg-gray-100 text-gray-600',
                'saldo'        => $saldo,
                'liquidado'    => $saldo <= 0,
                'updated_at'   => $order->updated_at?->format('d/m/Y'),
            ];
        });

        // ── Resumen financiero del mes ────────────────────────────────────
        $ingresosMes   = Payment::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->sum('amount');
        $totalAdeudado = $cuentasPorCobrar->sum('saldo');

        return Inertia::render('Finance/Index', [
            'cuentasPorCobrar' => $cuentasPorCobrar,
            'recentOrders'     => $recentOrders,
            'resumen' => [
                'ingresosMes'   => round($ingresosMes, 2),
                'totalAdeudado' => round($totalAdeudado, 2),
                'totalDeudores' => $cuentasPorCobrar->count(),
            ],
        ]);
    }
}
