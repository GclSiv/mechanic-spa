<?php

namespace App\Models;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Evidencia fotográfica o de video de una herramienta (UI-9).
 * Archivo en disco privado "local"; nunca se expone su ruta: se sirve por controlador autorizado.
 */
class ToolMedia extends Model
{
    public const TIPO_IMAGEN = 'image';
    public const TIPO_VIDEO  = 'video';

    public const DISCO = 'local';
    public const CARPETA = 'tool-media';

    /** Límites de Laravel en KB. El servidor PHP puede imponer un tope menor (upload_max_filesize / post_max_size). */
    public const MAX_IMAGEN_KB = 5120;   // 5 MB
    public const MAX_VIDEO_KB  = 51200;  // 50 MB

    public const MIMES_IMAGEN = ['image/jpeg', 'image/png', 'image/webp'];
    public const MIMES_VIDEO  = ['video/mp4', 'video/webm'];

    protected $table = 'tool_media';

    protected $fillable = [
        'tool_id', 'path', 'media_type', 'original_name', 'mime_type', 'size', 'uploaded_by',
    ];

    // La ruta interna del archivo nunca sale hacia las props de Inertia.
    protected $hidden = ['path'];

    protected $casts = [
        'size' => 'integer',
    ];

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /** Reglas compartidas del campo "evidencia". Sin nullable/required: cada caller decide la obligatoriedad. */
    public static function reglaEvidencia(): array
    {
        return [
            'file',
            'mimetypes:' . implode(',', array_merge(self::MIMES_IMAGEN, self::MIMES_VIDEO)),
            'max:' . self::MAX_VIDEO_KB,
            self::limiteImagen(),
        ];
    }

    /** Las imágenes tienen un límite propio, más bajo que el de video. */
    private static function limiteImagen(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ($value instanceof UploadedFile
                && str_starts_with((string) $value->getMimeType(), 'image/')
                && $value->getSize() > self::MAX_IMAGEN_KB * 1024) {
                $fail('La foto no puede superar ' . (self::MAX_IMAGEN_KB / 1024) . ' MB.');
            }
        };
    }

    /** Guarda el archivo en disco privado y registra su metadato. Devuelve el registro creado. */
    public static function guardar(Tool $herramienta, UploadedFile $archivo, ?int $usuarioId): self
    {
        $mime = (string) $archivo->getMimeType();
        $tipo = str_starts_with($mime, 'video/') ? self::TIPO_VIDEO : self::TIPO_IMAGEN;

        $ruta = Storage::disk(self::DISCO)->putFile(self::CARPETA . '/' . $herramienta->id, $archivo);

        return self::create([
            'tool_id'       => $herramienta->id,
            'path'          => $ruta,
            'media_type'    => $tipo,
            'original_name' => Str::limit(basename((string) $archivo->getClientOriginalName()), 200, ''),
            'mime_type'     => $mime,
            'size'          => $archivo->getSize(),
            'uploaded_by'   => $usuarioId,
        ]);
    }

    /** Borra el archivo físico y el registro. Solo se usa al quitar evidencia explícitamente. */
    public function eliminarArchivo(): void
    {
        Storage::disk(self::DISCO)->delete($this->path);
        $this->delete();
    }
}
