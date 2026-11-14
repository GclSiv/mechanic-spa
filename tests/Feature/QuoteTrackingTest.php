<?php

namespace Tests\Feature;

use App\Actions\Quotes\CreateQuoteAction;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithItems(): RepairOrder
    {
        $client   = Client::factory()->create(['phone' => '5551234567', 'rfc' => 'XAXX010101000']);
        $vehicle  = Vehicle::factory()->create(['client_id' => $client->id]);
        $recepcion = Recepcion::factory()->create(['client_id' => $client->id, 'vehicle_id' => $vehicle->id]);
        $order    = RepairOrder::factory()->create(['recepcion_id' => $recepcion->id, 'tax_rate' => 16]);

        RepairOrderItem::factory()->create([
            'repair_order_id' => $order->id, 'type' => 'labor',
            'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000,
        ]);
        RepairOrderItem::factory()->create([
            'repair_order_id' => $order->id, 'type' => 'part',
            'quantity' => 1, 'unit_price' => 500, 'subtotal' => 500,
        ]);

        return $order;
    }

    public function test_quote_snapshots_items_and_totals(): void
    {
        $order = $this->orderWithItems();

        $quote = app(CreateQuoteAction::class)->execute($order);

        // IVA 16% solo sobre el total, sin cambiar por tipo: (1000 + 500) * 1.16 = 1740
        $this->assertSame(1500.0, $quote->subtotal);
        $this->assertSame(240.0, $quote->tax);
        $this->assertSame(1740.0, $quote->total);
        $this->assertSame(Quote::BORRADOR, $quote->status);
        $this->assertCount(2, $quote->items);
    }

    public function test_quote_does_not_change_when_order_items_change_later(): void
    {
        $order = $this->orderWithItems();
        $quote = app(CreateQuoteAction::class)->execute($order);

        RepairOrderItem::factory()->create([
            'repair_order_id' => $order->id, 'type' => 'part',
            'quantity' => 1, 'unit_price' => 9999, 'subtotal' => 9999,
        ]);

        $quote->refresh();
        $this->assertSame(1740.0, $quote->total);
        $this->assertCount(2, $quote->items);
    }

    public function test_second_quote_gets_next_version_and_approval_replaces_open_ones(): void
    {
        $order = $this->orderWithItems();
        $action = app(CreateQuoteAction::class);

        $first  = $action->execute($order);
        $second = $action->execute($order);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);

        $second->markSent();
        $second->approve('whatsapp', 'Cliente Prueba');

        $this->assertSame(Quote::APROBADA, $second->fresh()->status);
        $this->assertSame(Quote::REEMPLAZADA, $first->fresh()->status);
        $this->assertSame($second->id, $order->fresh()->currentQuote()->id);
    }

    public function test_staff_can_create_send_and_approve_a_quote(): void
    {
        $order = $this->orderWithItems();
        $user  = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->post(route('repair-orders.quotes.store', $order))->assertRedirect();
        $quote = $order->fresh()->quotes()->first();
        $this->assertNotNull($quote);

        $this->actingAs($user)->patch(route('repair-orders.quotes.send', [$order, $quote]))->assertRedirect();
        $this->assertSame(Quote::ENVIADA, $quote->fresh()->status);

        $this->actingAs($user)->patch(route('repair-orders.quotes.approve', [$order, $quote]), [
            'approval_method' => 'telefono',
            'approved_by_name' => 'Juan Pérez',
        ])->assertRedirect();

        $quote->refresh();
        $this->assertSame(Quote::APROBADA, $quote->status);
        $this->assertSame('telefono', $quote->approval_method);
    }

    public function test_tracking_page_is_public_and_hides_private_client_data(): void
    {
        $order = $this->orderWithItems();
        app(CreateQuoteAction::class)->execute($order);

        $response = $this->get(route('tracking.show', ['token' => $order->tracking_token]));

        $response->assertOk();
        $response->assertDontSee('5551234567');
        $response->assertDontSee('XAXX010101000');
        $response->assertSee('&quot;component&quot;:&quot;Tracking\/Show', false);
    }

    public function test_tracking_rejects_unknown_token(): void
    {
        $this->get('/seguimiento/' . str_repeat('a', 48))->assertNotFound();
    }
}
