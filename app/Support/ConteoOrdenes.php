<?php

namespace App\Support;

use App\Actions\RepairOrders\EstadosFinancierosOrdenesAction;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;

/**
 * Conteos operativos compartidos por Dashboard y Órdenes. Una única definición de:
 * - flujo del taller: cinco estados operativos en UNA consulta agrupada por status_id;
 * - autorización pendiente: métrica DERIVADA (no es estado), tomada de la fuente financiera en lote.
 */
final class ConteoOrdenes
{
    /** Estados operativos del flujo del taller, en orden de proceso. */
    public const FLUJO = ['recibido', 'diagnostico', 'espera-piezas', 'en-reparacion', 'reparado'];

    /** Estados que no son "activos" para la operación del taller. */
    public const CERRADOS = ['entregado', 'rechazado'];

    /**
     * Conteo por estado del flujo. $mechanicId = null: alcance completo del taller (admin/owner).
     * Un entero: solo órdenes asignadas a ese mecánico (0 = sin registro, siempre vacío).
     *
     * @return array<string, int> siempre con las cinco claves, en orden de proceso
     */
    public static function flujo(?int $mechanicId = null): array
    {
        $slugPorId = RepairOrderStatus::whereIn('slug', self::FLUJO)->pluck('slug', 'id');
        $conteos   = array_fill_keys(self::FLUJO, 0);

        RepairOrder::whereIn('status_id', $slugPorId->keys())
            ->when($mechanicId !== null, fn ($q) => $q->where('mechanic_id', $mechanicId))
            ->selectRaw('status_id, COUNT(*) as total')
            ->groupBy('status_id')
            ->pluck('total', 'status_id')
            ->each(function ($total, $statusId) use (&$conteos, $slugPorId) {
                $conteos[$slugPorId[$statusId]] = (int) $total;
            });

        return $conteos;
    }

    /**
     * Órdenes activas (no entregadas ni rechazadas) con pendiente_autorizacion > 0.
     * Solo admin/owner: es dato financiero. Una sola llamada en lote, sin N+1.
     */
    public static function esperandoAutorizacion(): int
    {
        $idsActivas = RepairOrder::whereNotIn(
            'status_id',
            RepairOrderStatus::whereIn('slug', self::CERRADOS)->pluck('id')
        )->pluck('id')->all();

        return collect(app(EstadosFinancierosOrdenesAction::class)->execute($idsActivas))
            ->filter(fn (array $estado) => $estado['pendiente_autorizacion'] > 0)
            ->count();
    }
}
