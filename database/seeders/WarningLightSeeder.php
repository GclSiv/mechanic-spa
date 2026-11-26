<?php

namespace Database\Seeders;

use App\Models\WarningLight;
use App\Support\IconosTestigo;
use Illuminate\Database\Seeder;

/**
 * Testigos oficiales del sistema. Idempotente y seguro de repetir.
 *
 * - Crea los que faltan (firstOrCreate por código).
 * - Reconcilia SOLO la clave de icono (`icon`) de estos siete códigos oficiales, y solo si está en 'warning' o NULL. Antes, firstOrCreate no
 *   actualizaba filas existentes: los testigos creados sin icono quedaban con el default 'warning' de la
 *   migración 2026_11_29 y todos se veían como advertencia genérica.
 * - No toca nombre, orden ni activo de un testigo existente (cambios administrativos), ni los testigos
 *   creados por el cliente (cualquier código fuera de esta lista), ni recepcions.witnesses.
 */
class WarningLightSeeder extends Seeder
{
    /** [código, nombre, clave de icono, orden] de los testigos oficiales. */
    public const OFICIALES = [
        ['engine', 'Motor', 'engine', 1],
        ['abs', 'ABS', 'abs', 2],
        ['oil', 'Aceite', 'oil', 3],
        ['battery', 'Batería', 'battery', 4],
        ['temp', 'Temperatura', 'temp', 5],
        ['brake', 'Freno', 'brake', 6],
        ['tpms', 'TPMS', 'tpms', 7],
    ];

    public function run(): void
    {
        foreach (self::OFICIALES as [$code, $name, $icon, $sort]) {
            WarningLight::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'icon' => $icon, 'sort' => $sort, 'active' => true],
            );
        }

        $this->reconciliarIconos();
    }

    /**
     * Fija la clave de icono correcta en los siete códigos oficiales cuando es 'warning' o NULL.
     * Un icono distinto (válido, elegido) no se sobrescribe.
     * Devuelve cuántos registros corrigió. Idempotente: una segunda ejecución corrige 0.
     */
    public function reconciliarIconos(): int
    {
        $corregidos = 0;

        foreach (self::OFICIALES as [$code, , $icon]) {
            $corregidos += WarningLight::where('code', $code)
                // Solo icono genérico o nulo: un icono ya elegido (válido) nunca se sobrescribe.
                ->where(fn ($q) => $q->whereNull('icon')->orWhere('icon', IconosTestigo::ADVERTENCIA))
                ->update(['icon' => $icon]);
        }

        return $corregidos;
    }
}
