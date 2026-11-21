<?php

namespace App\Http\Controllers;

use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Actions\RepairOrders\EstadoFinancieroAction;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function ordenesCSV(Request $request, CalculateOrderTotalsAction $calculator)
    {
        // Filtro por mes (default: mes actual)
        $mes = $request->input('mes', now()->format('Y-m'));
        [$year, $month] = explode('-', $mes);

        $statusEntregado = RepairOrderStatus::where('slug', 'entregado')->first();

        $orders = RepairOrder::with([
            'recepcion.client',
            'recepcion.vehicle.brand',
            'recepcion.vehicle.vehicleModel',
            'mechanic',
        ])
        ->when($statusEntregado, fn($q) => $q->where('status_id', $statusEntregado->id))
        ->whereYear('created_at', $year)
        ->whereMonth('created_at', $month)
        ->orderByDesc('created_at')
        ->get();

        $filename = 'Ordenes_' . $mes . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($orders, $mes, $calculator) {
            $handle = fopen('php://output', 'w');
            $estado = app(EstadoFinancieroAction::class);

            // BOM para Excel en Windows
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Folio',
                'Fecha',
                'Cliente',
                'Teléfono',
                'Vehículo',
                'Placa',
                'Mecánico',
                'Subtotal',
                'Impuesto',
                'Total Orden',
                'Total Autorizado',
                'Total Pagado',
                'Saldo Autorizado',
                'Pendiente Autorización',
                'Estado',
            ]);

            $granTotal = 0.0;
            $granAutorizado = 0.0;
            $granPagado = 0.0;
            $granSaldo = 0.0;

            foreach ($orders as $order) {
                // Subtotal, IVA y total técnico salen de CalculateOrderTotalsAction (misma fuente que la orden)
                $desglose   = $calculator->calcular($order);
                // Autorizado, pagado, saldo y pendiente salen de EstadoFinancieroAction (misma fuente que el panel)
                $financiero = $estado->execute($order);

                $granTotal      += $desglose['total'];
                $granAutorizado += $financiero['total_autorizado'];
                $granPagado     += $financiero['total_pagado'];
                $granSaldo      += $financiero['saldo_autorizado'];

                fputcsv($handle, [
                    $order->folio ?? '#' . $order->id,
                    $order->created_at->format('d/m/Y'),
                    ($order->recepcion->client->first_name ?? '') . ' ' . ($order->recepcion->client->last_name ?? ''),
                    $order->recepcion->client->phone ?? '',
                    ($order->recepcion->vehicle->brand->name ?? '') . ' ' . ($order->recepcion->vehicle->vehicleModel->name ?? '') . ' ' . ($order->recepcion->vehicle->year ?? ''),
                    $order->recepcion->vehicle->plate ?? '',
                    $order->mechanic->name ?? 'Sin asignar',
                    number_format($desglose['subtotal'], 2),
                    number_format($desglose['tax'], 2),
                    number_format($desglose['total'], 2),
                    number_format($financiero['total_autorizado'], 2),
                    number_format($financiero['total_pagado'], 2),
                    number_format($financiero['saldo_autorizado'], 2),
                    number_format($financiero['pendiente_autorizacion'], 2),
                    'Entregado',
                ]);
            }

            // Totales
            fputcsv($handle, []);
            fputcsv($handle, [
                'TOTALES DEL MES: ' . $mes,
                '', '', '', '', '', '',
                '',
                '',
                number_format($granTotal, 2),
                number_format($granAutorizado, 2),
                number_format($granPagado, 2),
                number_format($granSaldo, 2),
                '',
                '',
            ]);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
