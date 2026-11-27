<?php

namespace App\Http\Controllers;

use App\Actions\RepairOrders\EstadoFinancieroAction;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\RepairOrder;
use Inertia\Inertia;

class FinanceController extends Controller
{
    public function index(EstadoFinancieroAction $estado)
    {
        // ── Cuentas por cobrar ────────────────────────────────────────────
        // Deuda exigible: saldo AUTORIZADO > 0. Solo órdenes con cotización aprobada, excepto rechazadas.
        $cuentasPorCobrar = RepairOrder::with([
                'status',
                'recepcion.client',
                'recepcion.vehicle.brand',
                'recepcion.vehicle.vehicleModel',
            ])
            ->whereHas('status', fn ($q) => $q->where('slug', '!=', 'rechazado'))
            ->whereHas('quotes', fn ($q) => $q->where('status', Quote::APROBADA))
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (RepairOrder $order) use ($estado) {
                $financiero = $estado->execute($order);
                if ($financiero['saldo_autorizado'] <= 0) return null;

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
                    'total'        => $financiero['total_autorizado'],
                    'pagado'       => $financiero['total_pagado'],
                    'saldo'        => $financiero['saldo_autorizado'],
                    'pendiente'    => $financiero['pendiente_autorizacion'],
                    'updated_at'   => $order->updated_at?->format('d/m/Y'),
                ];
            })
            ->filter()
            ->values();

        // ── Órdenes recientes (últimas 15) ────────────────────────────────
        $recentOrders = RepairOrder::with([
            'status', 'mechanic',
            'recepcion.client',
            'recepcion.vehicle.brand',
            'recepcion.vehicle.vehicleModel',
        ])
        ->orderByDesc('created_at')
        ->take(15)
        ->get()
        ->map(function (RepairOrder $order) use ($estado) {
            $financiero = $estado->execute($order);
            $saldo      = $financiero['saldo_autorizado'];

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
                'pendiente'    => $financiero['pendiente_autorizacion'],
                'liquidado'    => $financiero['total_autorizado'] > 0 && $saldo <= 0,
                'autorizado'   => $financiero['total_autorizado'] > 0,
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
