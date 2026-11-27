<?php

namespace App\Http\Controllers;

use App\Actions\Reports\IngresosPeriodoAction;
use App\Actions\Reports\ReporteV2Action;
use App\Models\Mechanic;
use App\Models\RepairOrderStatus;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FinancialReportController extends Controller
{
    /** Máximo de días por consulta para acotar el costo del reporte. */
    private const MAX_DIAS_RANGO = 731;

    /**
     * Reportes V2 (dashboard por rango). Solo owner/admin: la ruta tiene role:admin.
     * Los indicadores vienen de ReporteV2Action, que reutiliza FIN-002 y A2.
     */
    public function index(Request $request, ReporteV2Action $reporte)
    {
        $validated = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'estado_id' => ['nullable', 'integer', 'exists:repair_order_statuses,id'],
            'mecanico_id' => ['nullable', 'integer', 'exists:mechanics,id'],
        ]);

        $hoy = Carbon::today();
        $desde = Carbon::createFromFormat('Y-m-d', $validated['desde'] ?? $hoy->copy()->startOfMonth()->toDateString())->startOfDay();
        $hasta = Carbon::createFromFormat('Y-m-d', $validated['hasta'] ?? $hoy->copy()->endOfMonth()->toDateString())->startOfDay();

        if ($desde->diffInDays($hasta) > self::MAX_DIAS_RANGO) {
            throw ValidationException::withMessages(['hasta' => 'El rango máximo es de 2 años.']);
        }

        $estadoId = isset($validated['estado_id']) ? (int) $validated['estado_id'] : null;
        $mecanicoId = isset($validated['mecanico_id']) ? (int) $validated['mecanico_id'] : null;

        return Inertia::render('Reports/Index', [
            'filtros' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'estado_id' => $estadoId,
                'mecanico_id' => $mecanicoId,
            ],
            'reporte' => $reporte->execute($desde, $hasta, $estadoId, $mecanicoId),
            'estados' => RepairOrderStatus::orderBy('id')->get(['id', 'name']),
            'mecanicos' => Mechanic::orderBy('name')->get(['id', 'name']),
            'limite_ordenes' => 300,
        ]);
    }

    /**
     * Reporte mensual de INGRESOS: dinero cobrado (pagos válidos) dentro del mes. Ver IngresosPeriodoAction.
     * No usa totales técnicos ni montos autorizados como ingreso.
     */
    public function downloadPdf(Request $request, IngresosPeriodoAction $ingresos)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2020|max:2099',
            'lang'  => 'nullable|in:es,en',
        ]);

        $month = (int) $request->input('month');
        $year  = (int) $request->input('year');
        $lang  = $request->input('lang', 'es');

        App::setLocale(in_array($lang, ['es', 'en']) ? $lang : 'es');

        $datos = $ingresos->execute($month, $year);

        $pdf = Pdf::loadView('pdf.reporte_ingresos', [
            'rows'     => $datos['rows'],
            'totals'   => $datos['totals'],
            'month'    => $month,
            'year'     => $year,
            'settings' => Setting::current(),
            'lang'     => $lang,
        ])->setPaper('letter', 'landscape');

        $mesLabel = \Carbon\Carbon::createFromDate($year, $month, 1)
            ->locale($lang === 'en' ? 'en' : 'es')
            ->translatedFormat('F_Y');

        return $pdf->stream("Reporte_Ingresos_{$mesLabel}.pdf");
    }
}
