<?php

namespace App\Support;

use App\Models\Recepcion;
use App\Models\VehicleType;

/**
 * Diagrama de daños por tipo de unidad. Fuente única de verdad de las zonas válidas.
 *
 * - Cada diagrama tiene su propio conjunto de claves. Las claves no-automóvil llevan prefijo por diagrama,
 *   así ninguna zona de automóvil se reutiliza en otro diagrama y las etiquetas no chocan.
 * - Los daños guardados llevan snapshot (recepcions.damage_diagram) para que cambiar el tipo del vehículo
 *   después no reinterprete daños históricos.
 * - Sin snapshot: daños del esquema antiguo => car; sin daños => se usa el tipo actual.
 *
 * La copia en JS (resources/js/Components/DamageDiagrams/diagramas.js) debe mantener las mismas claves;
 * DamageDiagramZonasTest verifica esa paridad.
 */
final class DiagramaDano
{
    public const CAR = 'car';
    public const MOTORCYCLE = 'motorcycle';
    public const FORKLIFT = 'forklift';
    public const TRUCK = 'truck';
    public const HEAVY = 'heavy_generic';
    public const GENERIC = 'generic';

    public const ZONAS = [
        self::CAR => [
            'defensa_del', 'capo', 'parabrisas', 'techo', 'luneta', 'cajuela', 'defensa_tra',
            'puerta_fi', 'puerta_ti', 'puerta_fd', 'puerta_td', 'llanta_fi', 'llanta_fd', 'llanta_ti', 'llanta_td',
            'espejo_i', 'espejo_d',
        ],
        self::MOTORCYCLE => [
            'moto_frontal', 'moto_tanque', 'moto_asiento', 'moto_lateral_izq', 'moto_lateral_der',
            'moto_escape', 'moto_rueda_delantera', 'moto_rueda_trasera',
        ],
        self::FORKLIFT => [
            'fork_mastil', 'fork_horquillas', 'fork_cabina', 'fork_techo', 'fork_lateral_izq',
            'fork_lateral_der', 'fork_contrapeso', 'fork_ruedas',
        ],
        self::TRUCK => [
            'camion_frontal', 'camion_cofre_cabina', 'camion_lateral_izq', 'camion_lateral_der',
            'camion_caja_remolque', 'camion_techo', 'camion_ruedas_delanteras', 'camion_ruedas_traseras',
        ],
        self::HEAVY => [
            'maq_frontal', 'maq_posterior', 'maq_lateral_izq', 'maq_lateral_der', 'maq_cabina',
            'maq_implemento', 'maq_tren_rodaje_ruedas', 'maq_estructura',
        ],
        self::GENERIC => [
            'gen_frontal', 'gen_posterior', 'gen_lateral_izq', 'gen_lateral_der', 'gen_techo', 'gen_estructura',
        ],
    ];

    /** Diagrama para un tipo de vehículo. Sin tipo, o tipo sin diagrama asignado => generic (fallback seguro). */
    public static function paraTipo(?VehicleType $tipo): string
    {
        $diagrama = $tipo?->damage_diagram;

        return ($diagrama !== null && array_key_exists($diagrama, self::ZONAS)) ? $diagrama : self::GENERIC;
    }

    /** @return list<string> */
    public static function zonas(string $diagrama): array
    {
        return self::ZONAS[$diagrama] ?? self::ZONAS[self::GENERIC];
    }

    public static function zonaValida(string $diagrama, string $zona): bool
    {
        return in_array($zona, self::zonas($diagrama), true);
    }

    /**
     * Diagrama con el que debe mostrarse una recepción guardada.
     * Snapshot primero; daños legacy sin snapshot => car; sin daños => tipo actual del vehículo.
     */
    public static function deRecepcion(Recepcion $recepcion, ?VehicleType $tipoActual): string
    {
        if ($recepcion->damage_diagram !== null && array_key_exists($recepcion->damage_diagram, self::ZONAS)) {
            return $recepcion->damage_diagram;
        }

        if (! empty($recepcion->damages)) {
            return self::CAR;
        }

        return self::paraTipo($tipoActual);
    }
}
