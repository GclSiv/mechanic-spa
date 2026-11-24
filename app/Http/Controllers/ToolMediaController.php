<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use App\Models\ToolMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Evidencia de herramientas (UI-9). Solo admin/owner: las rutas llevan role:admin.
 * Los archivos viven en disco privado y se sirven solo desde aquí.
 */
class ToolMediaController extends Controller
{
    public function store(Request $request, Tool $tool)
    {
        $datos = $request->validate([
            'evidencia' => ['required', ...ToolMedia::reglaEvidencia()],
        ], [
            'evidencia.required'  => 'Selecciona una foto o un video.',
            'evidencia.mimetypes' => 'Formato no permitido. Usa JPEG, PNG, WEBP, MP4 o WebM.',
            'evidencia.max'       => 'El archivo supera el tamaño máximo permitido.',
            'evidencia.uploaded'  => 'El archivo no se pudo subir. Puede superar el límite del servidor.',
        ]);

        ToolMedia::guardar($tool, $datos['evidencia'], $request->user()?->id);

        return back()->with('success', 'Evidencia agregada.');
    }

    /**
     * BinaryFileResponse (no StreamedResponse): soporta peticiones Range, necesarias para
     * que el reproductor de video pueda avanzar y retroceder. Se muestra en línea, sin forzar descarga.
     */
    public function show(Tool $tool, ToolMedia $media)
    {
        abort_unless($media->tool_id === $tool->id, 404);

        $absoluta = Storage::disk(ToolMedia::DISCO)->path($media->path);
        abort_unless(is_file($absoluta), 404);

        $nombre = $media->original_name ?: 'evidencia';

        $respuesta = response()->file($absoluta, [
            'Content-Type'           => $media->mime_type,
            'Content-Disposition'    => (new ResponseHeaderBag)->makeDisposition('inline', $nombre, Str::ascii($nombre)),
            'X-Content-Type-Options' => 'nosniff',
        ]);

        // BinaryFileResponse marca la respuesta como pública por defecto; la evidencia es privada.
        $respuesta->setPrivate();
        $respuesta->headers->addCacheControlDirective('no-store');

        return $respuesta;
    }

    public function destroy(Tool $tool, ToolMedia $media)
    {
        abort_unless($media->tool_id === $tool->id, 404);

        $media->eliminarArchivo();

        return back()->with('success', 'Evidencia eliminada.');
    }
}
