<?php

namespace App\Http\Controllers;

use App\Models\Mechanic;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MechanicProductivityController extends Controller
{
    /**
     * Productividad de mecánicos por mes: órdenes terminadas, mano de obra,
     * comisión (sobre mano de obra sin IVA) y días promedio de servicio.
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

        $orders = RepairOrder::query()
            ->whereIn('status_id', $statusIds)
            ->whereNotNull('mechanic_id')
            ->whereYear('updated_at', $year)
            ->whereMonth('updated_at', $month)
            ->withSum(['items as labor_total' => function ($q) {
                $q->where('type', 'labor');
            }], 'subtotal')
            ->get();

        $byMechanic = $orders->groupBy('mechanic_id');

        $rows = Mechanic::orderBy('name')
            ->get(['id', 'name', 'commission_rate'])
            ->map(function (Mechanic $mechanic) use ($byMechanic) {
                $mechanicOrders = $byMechanic->get($mechanic->id, collect());

                $labor = round((float) $mechanicOrders->sum('labor_total'), 2);
                $rate  = (float) $mechanic->commission_rate;

                $days = $mechanicOrders
                    ->filter(fn ($o) => $o->entry_date && $o->delivery_date)
                    ->map(fn ($o) => abs(Carbon::parse($o->entry_date)->diffInDays(Carbon::parse($o->delivery_date))));

                return [
                    'name'            => $mechanic->name,
                    'orders'          => $mechanicOrders->count(),
                    'labor_total'     => $labor,
                    'commission_rate' => $rate,
                    'commission'      => round($labor * $rate / 100, 2),
                    'avg_days'        => $days->isNotEmpty() ? round($days->avg(), 1) : null,
                ];
            })
            ->values();

        $totals = [
            'orders'     => $rows->sum('orders'),
            'labor_total'=> round($rows->sum('labor_total'), 2),
            'commission' => round($rows->sum('commission'), 2),
        ];

        return Inertia::render('Reports/Mechanics', [
            'rows'   => $rows,
            'totals' => $totals,
            'month'  => $month,
            'year'   => $year,
        ]);
    }
}
