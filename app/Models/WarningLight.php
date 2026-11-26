<?php

namespace App\Models;

use App\Support\IconosTestigo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Testigo del tablero (catálogo). Las recepciones guardan su code en recepcions.witnesses.
 *
 * Icono: dos fuentes separadas y tipadas.
 *  - icon: clave de la biblioteca interna (App\Support\IconosTestigo). Por defecto "warning".
 *  - custom_icon_path: ruta INTERNA de un icono personalizado normalizado, en el disco privado.
 *    Nunca una URL ni una ruta escrita por el usuario; solo la fija el proceso de carga (futuro).
 *
 * Resolución: personalizado válido → biblioteca (icon) → "warning".
 */
class WarningLight extends Model
{
    /** Carpeta dentro del disco privado donde viven los iconos personalizados normalizados. */
    public const CARPETA_PERSONALIZADOS = 'testigos';

    /** Forma exacta de una ruta personalizada válida: carpeta + 32 hex + extensión permitida. */
    private const PATRON_RUTA = '#^testigos/[a-f0-9]{32}\.(png|webp|jpg)$#';

    protected $fillable = ['code', 'name', 'icon', 'custom_icon_path', 'sort', 'active'];

    protected $casts = ['active' => 'boolean'];

    protected static function booted(): void
    {
        // Un testigo usado por recepciones históricas no se borra: se desactiva.
        static::deleting(function (WarningLight $testigo) {
            if (self::enUsoEnRecepciones($testigo->code)) {
                throw new \DomainException('El testigo está usado en recepciones: desactívalo en lugar de eliminarlo.');
            }
        });

        // Al borrarlo (sin uso), también se borra su icono personalizado normalizado.
        static::deleted(function (WarningLight $testigo) {
            if ($testigo->custom_icon_path && self::rutaValida($testigo->custom_icon_path)) {
                Storage::disk('local')->delete($testigo->custom_icon_path);
            }
        });
    }

    /** Clave de biblioteca segura: una clave desconocida se resuelve a "warning". */
    public function iconoSeguro(): string
    {
        return IconosTestigo::resolver($this->icon);
    }

    /** Ruta absoluta del icono personalizado, solo si la ruta tiene forma válida y el archivo existe. */
    public function rutaIconoPersonalizado(): ?string
    {
        if (! $this->custom_icon_path || ! self::rutaValida($this->custom_icon_path)) {
            return null;
        }

        $disco = Storage::disk('local');

        return $disco->exists($this->custom_icon_path) ? $disco->path($this->custom_icon_path) : null;
    }

    /**
     * Fuente que debe usar la UI o el PDF:
     *  - ['tipo' => 'personalizado', 'ruta' => ...] si hay un icono personalizado válido;
     *  - ['tipo' => 'biblioteca', 'clave' => ...] en cualquier otro caso (con fallback "warning").
     *
     * @return array{tipo: string, ruta?: string, clave?: string}
     */
    public function fuenteIcono(): array
    {
        if ($ruta = $this->rutaIconoPersonalizado()) {
            return ['tipo' => 'personalizado', 'ruta' => $ruta];
        }

        return ['tipo' => 'biblioteca', 'clave' => $this->iconoSeguro()];
    }

    public static function rutaValida(?string $ruta): bool
    {
        return $ruta !== null && preg_match(self::PATRON_RUTA, $ruta) === 1;
    }

    /** ¿Alguna recepción (histórica o no) guarda este código en witnesses? */
    public static function enUsoEnRecepciones(string $code): bool
    {
        return Recepcion::whereJsonContains('witnesses', $code)->exists();
    }
}
