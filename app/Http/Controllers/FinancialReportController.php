<?php

namespace App\Http\Controllers;

use App\Actions\Reports\IngresosPeriodoAction;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;

class FinancialReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Reports/Index');
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
