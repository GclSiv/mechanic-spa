<?php

namespace App\Http\Controllers;

use App\Models\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Regeneración del enlace de seguimiento de una orden. Solo owner/admin (ruta con role:admin). */
class RepairOrderTrackingController extends Controller
{
    /**
     * Sustituye el token de la orden. El anterior deja de resolver de inmediato (no hay historial).
     * No toca estado, finanzas, cotizaciones ni pagos: solo la columna tracking_token.
     */
    public function regenerate(RepairOrder $order): RedirectResponse
    {
        DB::transaction(function () use ($order) {
            $order->forceFill(['tracking_token' => Str::random(48)])->save();
        });

        return back()->with('success', 'Enlace regenerado. El enlace anterior ya no funciona.');
    }
}
