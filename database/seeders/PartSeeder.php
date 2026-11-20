<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Part;
use Illuminate\Database\Seeder;

/**
 * Productos FICTICIOS de ejemplo para probar el sistema (precios y stock de demostración).
 * Reemplázalos por tu inventario real. Idempotente: se identifica por SKU.
 */
class PartSeeder extends Seeder
{
    public function run(): void
    {
        $parts = [
            ['category' => 'aceites',    'sku' => 'DEMO-ACE-001', 'name' => 'Aceite sintético 5W-30 (ejemplo)',  'cost' => 120.00, 'sale' => 210.00, 'stock' => 24, 'min' => 5],
            ['category' => 'aceites',    'sku' => 'DEMO-ACE-002', 'name' => 'Aceite semisintético 10W-40 (ejemplo)', 'cost' => 95.00,  'sale' => 165.00, 'stock' => 18, 'min' => 5],
            ['category' => 'filtros',    'sku' => 'DEMO-FIL-001', 'name' => 'Filtro de aceite (ejemplo)',         'cost' => 85.00,  'sale' => 150.00, 'stock' => 15, 'min' => 4],
            ['category' => 'filtros',    'sku' => 'DEMO-FIL-002', 'name' => 'Filtro de aire (ejemplo)',           'cost' => 60.00,  'sale' => 115.00, 'stock' => 12, 'min' => 4],
            ['category' => 'filtros',    'sku' => 'DEMO-FIL-003', 'name' => 'Filtro de gasolina (ejemplo)',       'cost' => 70.00,  'sale' => 130.00, 'stock' => 10, 'min' => 3],
            ['category' => 'frenos',     'sku' => 'DEMO-FRE-001', 'name' => 'Balatas delanteras (ejemplo)',        'cost' => 280.00, 'sale' => 480.00, 'stock' => 8,  'min' => 2],
            ['category' => 'frenos',     'sku' => 'DEMO-FRE-002', 'name' => 'Discos delanteros (par, ejemplo)',    'cost' => 650.00, 'sale' => 980.00, 'stock' => 4,  'min' => 1],
            ['category' => 'frenos',     'sku' => 'DEMO-FRE-003', 'name' => 'Líquido de frenos DOT 4 (ejemplo)',   'cost' => 75.00,  'sale' => 135.00, 'stock' => 14, 'min' => 4],
            ['category' => 'motor',      'sku' => 'DEMO-MOT-001', 'name' => 'Bujía de iridio (ejemplo)',          'cost' => 110.00, 'sale' => 190.00, 'stock' => 16, 'min' => 4],
            ['category' => 'motor',      'sku' => 'DEMO-MOT-002', 'name' => 'Banda de distribución (ejemplo)',    'cost' => 390.00, 'sale' => 620.00, 'stock' => 3,  'min' => 1],
            ['category' => 'suspension', 'sku' => 'DEMO-SUS-001', 'name' => 'Amortiguador delantero (ejemplo)',    'cost' => 520.00, 'sale' => 840.00, 'stock' => 4,  'min' => 2],
            ['category' => 'electrico',  'sku' => 'DEMO-ELE-001', 'name' => 'Batería 12V 60Ah (ejemplo)',          'cost' => 1200.00,'sale' => 1750.00,'stock' => 5,  'min' => 2],
            ['category' => 'general',    'sku' => 'DEMO-GEN-001', 'name' => 'Limpiaparabrisas (par, ejemplo)',     'cost' => 110.00, 'sale' => 185.00, 'stock' => 20, 'min' => 5],
        ];

        foreach ($parts as $p) {
            $categoryId = Category::where('slug', $p['category'])->value('id');

            Part::firstOrCreate(
                ['sku' => $p['sku']],
                [
                    'category_id'         => $categoryId,
                    'name'                => $p['name'],
                    'cost_price'          => $p['cost'],
                    'sale_price'          => $p['sale'],
                    'stock'               => $p['stock'],
                    'low_stock_threshold' => $p['min'],
                ]
            );
        }
    }
}
