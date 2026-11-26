<?php

namespace Database\Seeders;

use App\Models\FuelType;
use App\Models\Transmission;
use App\Models\VehicleSubtype;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;

/**
 * Catálogos de clasificación de vehículos. Idempotente: updateOrCreate por código, así que correrlo
 * de nuevo no duplica filas. No toca vehículos existentes ni les asigna valores (sin backfill).
 */
class VehicleCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['automobile', 'Automóvil', 'car', 1],
            ['suv', 'SUV', 'car', 2],
            ['pickup', 'Pickup', 'car', 3],
            ['van', 'Van / camioneta', 'car', 4],
            ['motorcycle', 'Motocicleta', 'motorcycle', 5],
            ['truck', 'Camión', 'truck', 6],
            ['tractor_truck', 'Tractocamión', 'truck', 7],
            ['tractor', 'Tractor', 'heavy_generic', 8],
            ['forklift', 'Montacargas', 'forklift', 9],
            ['heavy_machinery', 'Maquinaria pesada', 'heavy_generic', 10],
            ['other', 'Otro', null, 11],
        ];

        $ids = [];
        foreach ($tipos as [$code, $name, $diagrama, $sort]) {
            $tipo = VehicleType::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'damage_diagram' => $diagrama, 'sort' => $sort, 'active' => true],
            );
            $ids[$code] = $tipo->id;
        }

        $subtipos = [
            ['scooter', 'Scooter / Motoneta', 1],
            ['cruiser', 'Crucero', 2],
            ['sport', 'Deportiva', 3],
            ['naked', 'Naked', 4],
            ['dual_sport', 'Doble propósito / Enduro', 5],
            ['motocross', 'Motocross', 6],
            ['touring', 'Touring', 7],
            ['other', 'Otro', 8],
        ];

        foreach ($subtipos as [$code, $name, $sort]) {
            VehicleSubtype::updateOrCreate(
                ['code' => 'motorcycle_' . $code],
                ['vehicle_type_id' => $ids['motorcycle'], 'name' => $name, 'sort' => $sort, 'active' => true],
            );
        }

        $combustibles = [
            ['gasoline', 'Gasolina'], ['diesel', 'Diésel'], ['lpg', 'Gas LP'], ['cng', 'Gas natural'],
            ['hybrid', 'Híbrido'], ['plug_in_hybrid', 'Híbrido enchufable'], ['electric', 'Eléctrico'],
            ['other', 'Otro'], ['unspecified', 'No especificado'],
        ];
        foreach ($combustibles as $i => [$code, $name]) {
            FuelType::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'sort' => $i + 1, 'active' => true],
            );
        }

        $transmisiones = [
            ['manual', 'Manual'], ['automatic', 'Automática'], ['cvt', 'CVT'],
            ['dct', 'DCT / doble embrague'], ['amt', 'AMT / automatizada'],
            ['hydrostatic', 'Hidrostática'], ['other', 'Otra'], ['unspecified', 'No especificada'],
        ];
        foreach ($transmisiones as $i => [$code, $name]) {
            Transmission::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'sort' => $i + 1, 'active' => true],
            );
        }
    }
}
