<?php

namespace App\Http\Controllers;

use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Models\Payment;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
            'payments',
        ])
        ->when($statusEntregado, fn($q) => $q->where('status_id', $statusEntregado->id))
        ->whereYear('created_at', $year)
        ->whereMonth('created_at', $month)
        ->orderByDesc('created_at')
        ->get();

        $filename = 'JK_Automotive_Ordenes_' . $mes . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($orders, $mes, $calculator) {
            $handle = fopen('php://output', 'w');

            // BOM para Excel en Windows
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Encabezados
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
                'Total Pagado',
                'Saldo Pendiente',
                'Estado',
            ]);

            $granTotal = 0.0;
            $granPagado = 0.0;

            foreach ($orders as $order) {
                // Misma fuente que la pantalla de la orden: subtotal, IVA y total salen de CalculateOrderTotalsAction
                $desglose    = $calculator->calcular($order);
                $totalPagado = (float) $order->payments->sum('amount');
                $saldo       = $desglose['total'] - $totalPagado;

                $granTotal  += $desglose['total'];
                $granPagado += $totalPagado;

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
                    number_format($totalPagado, 2),
                    number_format(max(0, $saldo), 2),
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
                number_format($granPagado, 2),
                '',
                '',
            ]);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
