<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;

/**
 * Catálogo de marcas y modelos más comunes. Idempotente: se puede ejecutar varias veces.
 */
class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $vehicles = [
            'Nissan'     => ['Versa', 'Sentra', 'March', 'Tsuru', 'Kicks', 'Frontier', 'NP300', 'Altima', 'Tiida'],
            'Chevrolet'  => ['Aveo', 'Chevy', 'Spark', 'Beat', 'Onix', 'Silverado', 'Tornado', 'Trax', 'Captiva'],
            'Volkswagen' => ['Jetta', 'Gol', 'Vento', 'Polo', 'Virtus', 'Saveiro', 'Vocho', 'Tiguan', 'Bora'],
            'Toyota'     => ['Hilux', 'Corolla', 'Yaris', 'RAV4', 'Hiace', 'Tacoma', 'Camry', 'Sienna'],
            'Ford'       => ['Ranger', 'F-150', 'Figo', 'Fiesta', 'Focus', 'Mustang', 'Explorer', 'Escape'],
            'Honda'      => ['Civic', 'CR-V', 'HR-V', 'City', 'Accord', 'Fit'],
            'Mazda'      => ['Mazda3', 'Mazda2', 'CX-5', 'CX-3', 'BT-50'],
            'Hyundai'    => ['Grand i10', 'Creta', 'Tucson', 'Elantra', 'Accent'],
            'Kia'        => ['Rio', 'Forte', 'Sportage', 'Seltos', 'Soul'],
            'Jeep'       => ['Wrangler', 'Grand Cherokee', 'Compass', 'Renegade'],
            'Renault'    => ['Kwid', 'Duster', 'Stepway', 'Kangoo', 'Logan'],
        ];

        foreach ($vehicles as $brandName => $models) {
            $brand = Brand::firstOrCreate(['name' => $brandName]);

            foreach ($models as $modelName) {
                VehicleModel::firstOrCreate([
                    'brand_id' => $brand->id,
                    'name'     => $modelName,
                ]);
            }
        }
    }
}
