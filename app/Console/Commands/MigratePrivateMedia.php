<?php

namespace App\Console\Commands;

use App\Models\ClientPhoto;
use App\Models\FollowUp;
use App\Models\Recepcion;
use App\Models\RepairOrderPhoto;
use App\Support\ArchivoPrivado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Copia las fotos privadas históricas del disco público al disco privado, con la MISMA ruta relativa.
 *
 * Seguridad del proceso:
 * - Idempotente: una copia ya existente con el mismo contenido (sha256) se cuenta como "ya migrada".
 * - Nunca sobrescribe: si la copia privada existe con otro contenido, se reporta como error.
 * - Verifica la copia (tamaño y sha256) antes de considerarla válida.
 * - No modifica la base de datos: las rutas son las mismas en ambos discos.
 * - Tolera archivos faltantes (se cuentan, no interrumpen).
 * - El origen público solo se borra con --retirar, y únicamente después de verificar la copia privada.
 */
class MigratePrivateMedia extends Command
{
    protected $signature = 'app:migrate-private-media {--retirar : Borra el original público después de verificar la copia privada}';

    protected $description = 'Copia las fotos privadas históricas del disco público al disco privado (idempotente, sin tocar la base de datos)';

    public function handle(): int
    {
        $contadores = ['copiadas' => 0, 'ya_migradas' => 0, 'faltantes' => 0, 'errores' => 0, 'retiradas' => 0];
        $vistas = [];

        foreach ($this->rutas() as $path) {
            if (isset($vistas[$path])) {
                continue;
            }
            $vistas[$path] = true;

            $segura = ArchivoPrivado::rutaSegura($path);
            if ($segura === null) {
                $this->warn("Ruta no válida, omitida: {$path}");
                $contadores['errores']++;
                continue;
            }

            $origen = Storage::disk(ArchivoPrivado::DISCO_LEGADO)->path($segura);
            $destino = Storage::disk(ArchivoPrivado::DISCO)->path($segura);

            if (! is_file($origen)) {
                is_file($destino) ? $contadores['ya_migradas']++ : $contadores['faltantes']++;
                continue;
            }

            if (is_file($destino)) {
                if (hash_file('sha256', $destino) !== hash_file('sha256', $origen)) {
                    $this->error("Conflicto (no se sobrescribe): {$segura}");
                    $contadores['errores']++;
                    continue;
                }
                $contadores['ya_migradas']++;
            } else {
                File::ensureDirectoryExists(dirname($destino));
                if (! @copy($origen, $destino) || hash_file('sha256', $destino) !== hash_file('sha256', $origen)) {
                    if (is_file($destino)) {
                        @unlink($destino); // copia parcial o corrupta creada por este proceso
                    }
                    $this->error("Copia no verificada: {$segura}");
                    $contadores['errores']++;
                    continue;
                }
                $contadores['copiadas']++;
            }

            if ($this->option('retirar') && @unlink($origen)) {
                $contadores['retiradas']++;
            }
        }

        $this->table(
            ['Resultado', 'Archivos'],
            collect($contadores)->map(fn ($n, $k) => [$k, $n])->values()->all()
        );
        $this->line('La base de datos no se modificó: las rutas son idénticas en ambos discos.');

        return $contadores['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return \Generator<int, string> */
    private function rutas(): \Generator
    {
        foreach (ClientPhoto::query()->lazy() as $foto) {
            yield (string) $foto->path;
        }
        foreach (RepairOrderPhoto::query()->lazy() as $foto) {
            yield (string) $foto->path;
        }
        foreach (Recepcion::query()->whereNotNull('photos')->lazy() as $recepcion) {
            foreach ((array) $recepcion->photos as $path) {
                yield (string) $path;
            }
        }
        foreach (FollowUp::query()->whereNotNull('evidence_photos')->lazy() as $nota) {
            foreach ((array) $nota->evidence_photos as $path) {
                yield (string) $path;
            }
        }
    }
}
