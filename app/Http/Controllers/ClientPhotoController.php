<?php
namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClientPhotoController extends Controller
{
    // Subida múltiple
    public function store(Request $request, Client $client)
    {
        $request->validate([
            'photos.*' => 'required|image|mimes:jpg,jpeg,png|max:5120', // Max 5MB por foto
        ]);

        foreach ($request->file('photos') as $file) {
            $path = $file->store('recepciones/' . $client->id, 'public');
            
            $client->photos()->create([
                'path' => $path,
            ]);
        }

        return back()->with('success', 'Evidencia fotográfica guardada.');
    }

    // Eliminar foto física y registro
    public function destroy(ClientPhoto $photo)
    {
        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return back()->with('info', 'Foto eliminada correctamente.');
    }
}