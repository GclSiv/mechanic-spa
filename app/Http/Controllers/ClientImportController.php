<?php

namespace App\Http\Controllers;

use App\Actions\Clients\ImportClientsAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientImportController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        return Inertia::render('Clients/Import', ['result' => null]);
    }

    /**
     * Plantilla CSV con BOM (para que Excel abra los acentos bien) y separador punto y coma.
     */
    public function template(): StreamedResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return response()->streamDownload(function () {
            echo "\xEF\xBB\xBF";
            echo "nombre;apellidos;telefono;direccion;rfc;correo;pais\n";
            echo "Juan;Pérez López;5551234567;Av. Juárez 10, Oaxaca;XAXX010101000;juan@correo.com;MX\n";
        }, 'plantilla_clientes.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request, ImportClientsAction $action)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $result = $action->execute($request->file('file')->getRealPath());

        return Inertia::render('Clients/Import', ['result' => $result]);
    }
}
