<?php

namespace App\Http\Controllers;

use App\Models\Mechanic;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MechanicProductivityController extends Controller
{
    /**
     * Productividad de mecánicos por mes: órdenes terminadas, mano de obra y comisiones.
     *
     * Dos modelos conviven (UI-10 · Fase 2) y usan criterios de período distintos:
     * - legacy (histórico): órdenes Reparado/Entregado cuya updated_at cae en el mes. Comisión =
     *   mano de obra de las órdenes donde el mecánico es responsable × commission_rate. Sin reinterpretar.
     * - fixed (nuevo): conceptos de mano de obra cuyo created_at cae en el mes (fecha de registro del
     *   trabajo). Comisión = commission_amount_snapshot, UNA vez, para el mecánico ejecutor. No depende
     *   del estado ni de las actualizaciones posteriores de la orden (pagos, is_paid, nuevos conceptos).
     *
     * Una orden rechazada no aporta comisión fija (no se paga). totals.orders cuenta órdenes distintas
     * del período, no la suma de participaciones por mecánico.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2020|max:2099',
        ]);

        $month = (int) $validated['month'];
        $year  = (int) $validated['year'];

        $statusIds = RepairOrderStatus::whereIn('slug', ['reparado', 'entregado'])->pluck('id');
        $rechazadoIds = RepairOrderStatus::where('slug', 'rechazado')->pluck('id');

        // Legacy: órdenes del período por updated_at (criterio histórico, sin cambios).
        $ordenes = RepairOrder::query()
            ->whereIn('status_id', $statusIds)
            ->whereYear('updated_at', $year)
            ->whereMonth('updated_at', $month)
            ->with(['items' => fn ($q) => $q->where('type', 'labor')
                ->where('commission_model', RepairOrderItem::MODELO_LEGACY)
                ->select(['id', 'repair_order_id', 'type', 'subtotal', 'mechanic_id'])])
            ->get(['id', 'mechanic_id', 'entry_date', 'delivery_date']);

        // Fijo: conceptos registrados en el período por created_at, sin importar el estado actual de la orden.
        $conceptosFijos = RepairOrderItem::query()
            ->where('type', 'labor')
            ->where('commission_model', RepairOrderItem::MODELO_FIXED)
            ->whereNotNull('mechanic_id')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            // Orden rechazada: su comisión fija no se paga. Abierta/reparada/entregada sí cuentan.
            ->whereHas('repairOrder', fn ($q) => $q->whereNotIn('status_id', $rechazadoIds))
            ->with('repairOrder:id,mechanic_id,entry_date,delivery_date')
            ->get(['id', 'repair_order_id', 'mechanic_id', 'subtotal', 'commission_amount_snapshot']);

        $rows = Mechanic::orderBy('name')
            ->get(['id', 'name', 'commission_rate'])
            ->map(function (Mechanic $mechanic) use ($ordenes, $conceptosFijos) {
                $id = (int) $mechanic->id;

                // Legacy: mano de obra histórica de las órdenes donde es responsable.
                $ordenesResponsable = $ordenes->where('mechanic_id', $id);
                $laborLegacy = round((float) $ordenesResponsable->sum(fn ($o) => $o->items->sum('subtotal')), 2);
                $rate = (float) $mechanic->commission_rate;
                $comisionLegacy = round($laborLegacy * $rate / 100, 2);

                // Fijo: conceptos ejecutados por este mecánico (no por el responsable de la orden).
                $propios = $conceptosFijos->where('mechanic_id', $id);
                $laborFijo = round((float) $propios->sum('subtotal'), 2);
                $comisionFija = round((float) $propios->sum('commission_amount_snapshot'), 2);

                // Participación: órdenes donde es responsable o ejecutó algún concepto fijo (sin duplicar).
                $ordenesMecanico = $ordenesResponsable->values()
                    ->concat($propios->map(fn ($i) => $i->repairOrder))
                    ->unique('id');

                $days = $ordenesMecanico
                    ->filter(fn ($o) => $o->entry_date && $o->delivery_date)
                    ->map(fn ($o) => abs(Carbon::parse($o->entry_date)->diffInDays(Carbon::parse($o->delivery_date))));

                return [
                    'name'              => $mechanic->name,
                    'orders'            => $ordenesMecanico->count(),
                    'labor_total'       => round($laborLegacy + $laborFijo, 2),
                    'commission_rate'   => $rate,
                    'commission_legacy' => $comisionLegacy,
                    'commission_fixed'  => $comisionFija,
                    'commission'        => round($comisionLegacy + $comisionFija, 2),
                    'avg_days'          => $days->isNotEmpty() ? round($days->avg(), 1) : null,
                ];
            })
            ->values();

        // Órdenes distintas del período: legacy por updated_at ∪ órdenes con conceptos fijos registrados en el mes.
        $ordenesTotales = $ordenes->pluck('id')
            ->merge($conceptosFijos->pluck('repair_order_id'))
            ->unique()
            ->count();

        $totals = [
            'orders'            => $ordenesTotales,
            'labor_total'       => round($rows->sum('labor_total'), 2),
            'commission_legacy' => round($rows->sum('commission_legacy'), 2),
            'commission_fixed'  => round($rows->sum('commission_fixed'), 2),
            'commission'        => round($rows->sum('commission'), 2),
        ];

        return Inertia::render('Reports/Mechanics', [
            'rows'   => $rows,
            'totals' => $totals,
            'month'  => $month,
            'year'   => $year,
        ]);
    }
}
