<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\RepairOrder;
use Illuminate\Http\Request;

class FollowUpController extends Controller
{
    public function store(Request $request, RepairOrder $order)
    {
        $request->validate([
            'mechanic_id'  => 'required|exists:mechanics,id',
            'description'  => 'required|string|max:2000',
            'date'         => 'required|date',
            'photos'       => 'nullable|array|max:10',
            'photos.*'     => 'file|image|max:5120', // 5 MB por foto
        ]);

        // Subir fotos de evidencia si las hay
        $photoPaths = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $photoPaths[] = $photo->store('follow_ups', 'public');
            }
        }

        $order->followUps()->create([
            'mechanic_id'     => $request->mechanic_id,
            'description'     => $request->description,
            'date'            => $request->date,
            'evidence_photos' => !empty($photoPaths) ? $photoPaths : null,
        ]);

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Nota de seguimiento agregada correctamente.');
    }

    public function destroy(RepairOrder $order, FollowUp $followUp)
    {
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
