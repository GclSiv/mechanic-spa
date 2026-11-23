<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\RepairOrder;
use Illuminate\Http\Request;

class FollowUpController extends Controller
{
    public function store(Request $request, RepairOrder $order)
    {
        abort_if($order->estaCerrada(), 403, 'Esta orden está cerrada. No se pueden agregar notas.');

        $request->validate([
            'mechanic_id'  => ($request->user()->isAdmin() ? 'required' : 'nullable') . '|exists:mechanics,id',
            'description'  => 'required|string|max:2000',
            'date'         => 'required|date',
            'photos'       => 'nullable|array|max:10',
            'photos.*'     => 'file|image|max:5120', // 5 MB por foto
        ]);

        // Autor de la nota: admin/dueño elige mecánico; el mecánico escribe siempre con su propia identidad.
        if ($request->user()->isAdmin()) {
            $mechanicId = (int) $request->mechanic_id;
        } else {
            $propio = $request->user()->mechanic?->id;
            abort_unless($propio, 403, 'Tu usuario no está vinculado a un registro de mecánico.');
            abort_if(
                $request->filled('mechanic_id') && (int) $request->mechanic_id !== (int) $propio,
                403,
                'No puedes registrar notas a nombre de otro mecánico.'
            );
            $mechanicId = (int) $propio;
        }

        // Subir fotos de evidencia si las hay
        $photoPaths = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $photoPaths[] = $photo->store('follow_ups', 'public');
            }
        }

        $order->followUps()->create([
            'mechanic_id'     => $mechanicId,
            'description'     => $request->description,
            'date'            => $request->date,
            'evidence_photos' => !empty($photoPaths) ? $photoPaths : null,
        ]);

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Nota de seguimiento agregada correctamente.');
    }

    public function destroy(Request $request, RepairOrder $order, FollowUp $followUp)
    {
        // La nota debe pertenecer a esta orden.
        abort_unless((int) $followUp->repair_order_id === (int) $order->id, 404);

        // Orden cerrada = histórica: nadie (ni admin) elimina notas.
        abort_if($order->estaCerrada(), 403, 'Esta orden está cerrada. No se pueden eliminar notas ni evidencias.');

        if (! $request->user()->isAdmin()) {
            // El mecánico solo elimina sus propias notas.
            $propio = $request->user()->mechanic?->id;
            abort_unless(
                $propio && (int) $followUp->mechanic_id === (int) $propio,
                403,
                'Solo puedes eliminar tus propias notas.'
            );
        }

        // Eliminar archivos del disco si existen
        if ($followUp->evidence_photos) {
            foreach ($followUp->evidence_photos as $path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
            }
        }

        $followUp->delete();

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Nota eliminada.');
    }
}
