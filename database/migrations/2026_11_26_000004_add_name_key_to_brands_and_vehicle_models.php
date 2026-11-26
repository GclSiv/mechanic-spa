<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


/**
 * Fase B · Clave normalizada para evitar duplicados.
 *
 * - brands.name_key único.
 * - vehicle_models.name_key único por marca (brand_id, name_key).
 * - Backfill desde el nombre con la misma regla que App\Support\NombreClave. Si el backfill
 *   encuentra duplicados reales, la migración se detiene con el listado: no fusiona ni borra nada.
 * - Los nombres visibles no cambian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', fn (Blueprint $t) => $t->string('name_key', 255)->nullable()->after('name'));
        Schema::table('vehicle_models', fn (Blueprint $t) => $t->string('name_key', 255)->nullable()->after('name'));

        $this->rellenar('brands', null);
        $this->rellenar('vehicle_models', 'brand_id');

        Schema::table('brands', fn (Blueprint $t) => $t->unique('name_key', 'brands_name_key_unique'));
        Schema::table('vehicle_models', fn (Blueprint $t) => $t->unique(['brand_id', 'name_key'], 'vehicle_models_brand_name_key_unique'));
    }

    public function down(): void
    {
        Schema::table('vehicle_models', function (Blueprint $t) {
            $t->dropUnique('vehicle_models_brand_name_key_unique');
            $t->dropColumn('name_key');
        });
        Schema::table('brands', function (Blueprint $t) {
            $t->dropUnique('brands_name_key_unique');
            $t->dropColumn('name_key');
        });
    }

    private function rellenar(string $tabla, ?string $grupo): void
    {
        $columnas = $grupo ? ['id', 'name', $grupo] : ['id', 'name'];
        $filas = DB::table($tabla)->get($columnas);

        $vistos = [];
        $claves = [];
        foreach ($filas as $fila) {
            $clave = $this->clave($fila->name);
            $llave = ($grupo ? $fila->{$grupo} : 'all') . '|' . $clave;

            if (isset($vistos[$llave])) {
                throw new \RuntimeException(
                    "Duplicados en {$tabla} con la misma clave \"{$clave}\" (ids {$vistos[$llave]} y {$fila->id}). Resuélvalos antes de migrar."
                );
            }
            $vistos[$llave] = $fila->id;
            $claves[$fila->id] = $clave;
        }

        foreach ($claves as $id => $clave) {
            DB::table($tabla)->where('id', $id)->update(['name_key' => $clave]);
        }
    }

    /** Misma regla que App\Support\NombreClave::de (copiada para que la migración no dependa de código de app). */
    private function clave(?string $texto): string
    {
        $acentos = ['á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ñ' => 'n'];

        $clave = mb_strtolower(trim((string) $texto));
        $clave = preg_replace('/\s+/u', ' ', $clave);

        return strtr($clave, $acentos);
    }
};
