<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientPhoto;
use App\Support\ArchivoPrivado;
use Illuminate\Http\Request;

class ClientPhotoController extends Controller
{
    // Subida múltiple (disco privado)
    public function store(Request $request, Client $client)
    {
        $request->validate([
            'photos.*' => 'required|image|mimes:jpg,jpeg,png|max:5120', // Max 5MB por foto
        ]);

        foreach ($request->file('photos') as $file) {
            $path = ArchivoPrivado::guardar($file, 'clientes/' . $client->id);

            $client->photos()->create([
                'path' => $path,
            ]);
        }

        return back()->with('success', 'Evidencia fotográfica guardada.');
    }

    /** Foto del cliente: solo admin/owner (ruta con role:admin) y solo si pertenece a ese cliente. */
    public function show(Client $client, ClientPhoto $photo)
    {
        abort_unless((int) $photo->client_id === (int) $client->id, 404);

        return ArchivoPrivado::respuesta($photo->path, basename((string) $photo->path));
    }

    // Eliminar foto física y registro
    public function destroy(ClientPhoto $photo)
    {
        ArchivoPrivado::eliminar($photo->path);
        $photo->delete();

        return back()->with('info', 'Foto eliminada correctamente.');
    }
}
