<?php

namespace App\Http\Controllers;

use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;

class FinancialReportController extends Controller
{
    /**
     * Muestra la vista Vue del generador de reportes.
     */
    public function index()
    {
        return Inertia::render('Reports/Index');
    }

    /**
     * Genera y transmite el PDF de reporte de ingresos mensual.
     */
    public function downloadPdf(Request $request, CalculateOrderTotalsAction $calculator)
    {
        // ── 1. Validación de parámetros ───────────────────────────────────────
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2020|max:2099',
            'lang'  => 'nullable|in:es,en',
        ]);

        $month = (int) $request->input('month');
        $year  = (int) $request->input('year');
        $lang  = $request->input('lang', 'es');

        // ── 2. Locale del PDF ─────────────────────────────────────────────────
        App::setLocale(in_array($lang, ['es', 'en']) ? $lang : 'es');

        // ── 3. Obtener los slugs "Reparado" y "Entregado" ─────────────────────
        $statusIds = RepairOrderStatus::whereIn('slug', ['reparado', 'entregado'])
            ->pluck('id');

        // ── 4. Consultar órdenes del mes/año con las relaciones necesarias ────
        $orders = RepairOrder::with([
            'status',
            'items',
            'recepcion.client',
            'recepcion.vehicle.brand',
            'recepcion.vehicle.vehicleModel',
        ])
        ->whereIn('status_id', $statusIds)
        ->whereYear('updated_at', $year)
        ->whereMonth('updated_at', $month)
        ->orderBy('updated_at')
        ->get();

        // ── 5. Calcular totales por orden usando la Action existente ──────────
        $rows = $orders->map(function (RepairOrder $order) use ($calculator) {
            $breakdown = $calculator->execute($order);
            return [
                'folio'          => $order->folio ?? ('ORD-' . str_pad($order->id, 4, '0', STR_PAD_LEFT)),
                'fecha'          => $order->updated_at
                    ? $order->updated_at->format('d/m/Y')
                    : '—',
                'cliente'        => trim(
                    ($order->recepcion->client->first_name ?? '') . ' ' .
                    ($order->recepcion->client->last_name  ?? '')
                ) ?: '—',
                'vehiculo'       => trim(
                    ($order->recepcion->vehicle?->brand?->name           ?? '') . ' ' .
                    ($order->recepcion->vehicle?->vehicleModel?->name    ?? '') . ' ' .
                    ($order->recepcion->vehicle?->year                   ?? '')
                ) ?: '—',
                'parts_subtotal' => $breakdown['parts_subtotal'],
                'labor_subtotal' => $breakdown['labor_subtotal'],
                'tax'            => $breakdown['tax'],
                'tax_rate'       => $breakdown['tax_rate'],
                'total'          => $breakdown['total'],
            ];
        });

        // ── 6. Sumar columnas para la fila de TOTALES GENERALES ───────────────
        $totals = [
            'parts_subtotal' => round($rows->sum('parts_subtotal'), 2),
            'labor_subtotal' => round($rows->sum('labor_subtotal'), 2),
            'tax'            => round($rows->sum('tax'),            2),
            'total'          => round($rows->sum('total'),          2),
        ];

        // ── 7. Configuración del taller ───────────────────────────────────────
        $settings = Setting::first();

        // ── 8. Renderizar y transmitir el PDF ────────────────────────────────
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reporte_ingresos', [
            'rows'     => $rows,
            'totals'   => $totals,
            'month'    => $month,
            'year'     => $year,
            'settings' => $settings,
            'lang'     => $lang,
        ])->setPaper('letter', 'landscape');

        $mesLabel  = \Carbon\Carbon::createFromDate($year, $month, 1)
            ->locale($lang === 'en' ? 'en' : 'es')
            ->translatedFormat('F_Y');

        return $pdf->stream("Reporte_Ingresos_{$mesLabel}.pdf");
    }
}
