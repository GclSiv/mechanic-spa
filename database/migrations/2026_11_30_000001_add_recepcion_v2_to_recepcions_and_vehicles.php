<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recepción V2. Solo aditiva: no borra ni modifica columnas legacy (vin, miles, inventory).
 * La copia de histórico es idempotente y deja intactas las columnas origen.
 */
return new class extends Migration
{
    /** Códigos fijos del inventario anterior y su nombre visible. */
    private const INVENTARIO_LEGACY = [
        'llanta'      => 'Llanta de refacción',
        'gato'        => 'Gato',
        'estereo'     => 'Estéreo',
        'tapetes'     => 'Tapetes',
        'herramienta' => 'Herramienta',
    ];

    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('identifier_type', 20)->nullable()->after('vin');
            $table->string('identifier_value', 100)->nullable()->after('identifier_type');
        });

        Schema::table('recepcions', function (Blueprint $table) {
            $table->string('reading_type', 5)->nullable()->after('miles');
            $table->decimal('reading_value', 12, 2)->nullable()->after('reading_type');
            $table->string('tire_size', 60)->nullable()->after('reading_value');
        });

        Schema::create('recepcion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recepcion_id')->constrained('recepcions')->cascadeOnDelete();
            $table->string('code', 30)->nullable();
            $table->string('name', 120);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit', 30)->nullable();
            $table->string('observation', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $this->copiarHistorico();
    }

    public function down(): void
    {
        Schema::dropIfExists('recepcion_items');

        Schema::table('recepcions', function (Blueprint $table) {
            $table->dropColumn(['reading_type', 'reading_value', 'tire_size']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['identifier_type', 'identifier_value']);
        });
    }

    private function copiarHistorico(): void
    {
        // 1. Identificador histórico: NO se copia desde vin. vehicles.identifier_type e identifier_value quedan NULL
        //    en registros existentes; vin no cambia. La app resuelve el identificador histórico por fallback a vin.

        // 2. Kilometraje histórico: NO se copia ni se asigna unidad. miles queda intacto como lectura histórica
        //    sin unidad conocida (reading_type y reading_value quedan nulos). Ver Recepcion::lecturaPdf.

        // 3. Inventario fijo histórico => elementos recibidos. Si ya hay elementos, no se duplica. inventory no cambia.
        DB::table('recepcions')
            ->whereNotNull('inventory')
            ->where('inventory', '<>', '')
            ->where('inventory', '<>', '[]')
            ->orderBy('id')
            ->chunkById(200, function ($filas) {
                foreach ($filas as $fila) {
                    if (DB::table('recepcion_items')->where('recepcion_id', $fila->id)->exists()) {
                        continue;
                    }

                    $codigos = json_decode((string) $fila->inventory, true);
                    if (! is_array($codigos)) {
                        continue;
                    }

                    $ahora = now();
                    $elementos = [];
                    $orden = 0;
                    foreach ($codigos as $codigo) {
                        if (! isset(self::INVENTARIO_LEGACY[$codigo])) {
                            continue;
                        }
                        $elementos[] = [
                            'recepcion_id' => $fila->id,
                            'code'         => $codigo,
                            'name'         => self::INVENTARIO_LEGACY[$codigo],
                            'quantity'     => 1,
                            'unit'         => null,
                            'observation'  => null,
                            'sort_order'   => $orden++,
                            'created_at'   => $ahora,
                            'updated_at'   => $ahora,
                        ];
                    }

                    if ($elementos) {
                        DB::table('recepcion_items')->insert($elementos);
                    }
                }
            });
    }
};
