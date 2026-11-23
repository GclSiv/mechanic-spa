<?php

namespace App\Http\Controllers;

use App\Models\RepairOrder;
use App\Models\RepairOrderPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Fotos del avance del vehículo. El cliente las ve en su enlace de seguimiento.
 */
class RepairOrderPhotoController extends Controller
{
    private const ESTADOS_CON_FOTOS = ['diagnostico', 'espera-piezas', 'en-reparacion', 'reparado'];

    public function store(Request $request, RepairOrder $order)
    {
        // Regla de dominio: una orden cerrada es evidencia histórica de solo lectura.
        abort_if($order->estaCerrada(), 403, 'Esta orden está cerrada. No se pueden subir fotos de avance.');

        if (! in_array($order->status?->slug, self::ESTADOS_CON_FOTOS, true)) {
            return back()->withErrors(['photo' => 'Las fotos se suben cuando el vehículo está en diagnóstico, espera de piezas, reparación o listo.']);
        }

        $data = $request->validate([
            'photo' => 'required|image|max:5120',
            'note'  => 'nullable|string|max:255',
        ]);

        $path = $data['photo']->store('avance/' . $order->id, 'public');

        $order->avancePhotos()->create([
            'path' => $path,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Foto agregada al avance.');
    }

    public function show(RepairOrder $order, RepairOrderPhoto $photo)
    {
        abort_unless($photo->repair_order_id === $order->id, 404);

        return Storage::disk('public')->response($photo->path);
    }

    public function destroy(Request $request, RepairOrder $order, RepairOrderPhoto $photo)
    {
        abort_unless($photo->repair_order_id === $order->id, 404);
        // Regla de dominio: nadie (ni admin) elimina evidencia de una orden cerrada.
        abort_if($order->estaCerrada(), 403, 'Esta orden está cerrada. No se pueden eliminar fotos de avance.');

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return back()->with('success', 'Foto eliminada.');
    }
}
