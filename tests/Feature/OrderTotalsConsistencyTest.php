<?php

namespace Tests\Feature;

use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Models\Payment;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Pantalla de la orden, saldo y exportación deben obtener subtotal, IVA y total
 * de la misma fuente (CalculateOrderTotalsAction::calcular).
 */
class OrderTotalsConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /** Refacción 100 + mano de obra 200, IVA 16 %: subtotal 300, IVA 48, total 348. */
    private function entregadaConPagoParcial(): RepairOrder
    {
        $entregado = RepairOrderStatus::query()->firstOrCreate(
            ['slug' => 'entregado'], ['name' => 'Entregado', 'color_class' => 'bg-gray-500']
        );

        $order = RepairOrder::factory()->create(['tax_rate' => 16, 'status_id' => $entregado->id]);

        RepairOrderItem::create([
            'repair_order_id' => $order->id, 'type' => 'part', 'description' => 'Balata',
            'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100,
        ]);
        RepairOrderItem::create([
            'repair_order_id' => $order->id, 'type' => 'labor', 'description' => 'Mano de obra',
            'quantity' => 1, 'unit_price' => 200, 'subtotal' => 200,
        ]);

        // A2: el pago solo puede registrarse contra lo autorizado
        app(\App\Actions\Quotes\CreateQuoteAction::class)->execute($order)->approve('presencial', 'Cliente de prueba');
        Payment::create(['repair_order_id' => $order->id, 'amount' => 100, 'payment_method' => 'Cash']);

        return $order;
    }

    /** @return array<string, string> Fila de la exportación indexada por encabezado. */
    private function filaDeExportacion(string $folio): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $contenido = $this->actingAs($admin)
            ->get(route('export.ordenes', ['mes' => now()->format('Y-m')]))
            ->assertOk()
            ->streamedContent();

        $lineas = array_values(array_filter(explode("\n", ltrim($contenido, "\xEF\xBB\xBF"))));
        $encabezados = str_getcsv(array_shift($lineas));

        foreach ($lineas as $linea) {
            $celdas = str_getcsv($linea);
            if (($celdas[0] ?? null) === $folio) {
                return array_combine($encabezados, $celdas);
            }
        }

        $this->fail("La orden {$folio} no aparece en la exportación.");
    }

    public function test_order_screen_saldo_and_export_use_the_same_totals(): void
    {
        $order = $this->entregadaConPagoParcial();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        // 1. Pantalla de la orden
        $this->actingAs($admin)
            ->get(route('repair-orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('financial_breakdown.subtotal', 300)
                ->where('financial_breakdown.tax', 48)
                ->where('financial_breakdown.total', 348)
            );

        // 2. Saldo: total de la orden menos pagos (lo que usa el bloqueo de entrega)
        $total  = (float) $order->fresh()->estimated_cost;
        $pagado = (float) $order->payments()->sum('amount');
        $this->assertSame(348.0, $total);
        $this->assertSame(248.0, round($total - $pagado, 2));

        // 3. Exportación
        $fila = $this->filaDeExportacion($order->folio);
        $this->assertSame('300.00', $fila['Subtotal']);
        $this->assertSame('48.00', $fila['Impuesto']);
        $this->assertSame('348.00', $fila['Total Orden']);
        $this->assertSame('100.00', $fila['Total Pagado']);
        $this->assertSame('248.00', $fila['Saldo Autorizado']);
    }

    public function test_export_recalculates_from_items_not_from_a_stale_stored_total(): void
    {
        $order = $this->entregadaConPagoParcial();

        // Un total guardado desactualizado no debe alterar la exportación
        $order->update(['estimated_cost' => 1]);

        $fila = $this->filaDeExportacion($order->folio);

        $this->assertSame('348.00', $fila['Total Orden']);
        $this->assertSame('248.00', $fila['Saldo Autorizado']);
    }

    public function test_calcular_does_not_persist_anything(): void
    {
        $order = RepairOrder::factory()->create(['tax_rate' => null, 'estimated_cost' => 0]);
        RepairOrderItem::create([
            'repair_order_id' => $order->id, 'type' => 'part', 'description' => 'Filtro',
            'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100,
        ]);

        $desglose = app(CalculateOrderTotalsAction::class)->calcular($order);

        $this->assertSame(116.0, $desglose['total']);
        $this->assertNull($order->fresh()->tax_rate);
        $this->assertSame(0.0, (float) $order->fresh()->estimated_cost);
    }
}
