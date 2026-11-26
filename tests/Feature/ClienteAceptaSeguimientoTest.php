<?php

namespace Tests\Feature;

use App\Actions\Quotes\CreateQuoteAction;
use App\Actions\RepairOrders\EstadoFinancieroAction;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cliente acepta o rechaza desde el enlace público de seguimiento (cobertura del flujo completo). */
class ClienteAceptaSeguimientoTest extends TestCase
{
    use RefreshDatabase;

    private function orden(?string $slugEstado = null): RepairOrder
    {
        $client = Client::factory()->create(['phone' => '5551234567', 'rfc' => 'XAXX010101000']);
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $recepcion = Recepcion::factory()->create(['client_id' => $client->id, 'vehicle_id' => $vehicle->id]);

        $attrs = ['recepcion_id' => $recepcion->id, 'tax_rate' => 16];
        if ($slugEstado !== null) {
            $attrs['status_id'] = RepairOrderStatus::firstOrCreate(
                ['slug' => $slugEstado],
                ['name' => $slugEstado, 'color_class' => 'bg-gray-500'],
            )->id;
        }
        $order = RepairOrder::factory()->create($attrs);

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

    private function enviada(RepairOrder $order): Quote
    {
        $quote = app(CreateQuoteAction::class)->execute($order);
        $quote->markSent();

        return $quote->fresh();
    }

    private function aceptar(RepairOrder $order, Quote $quote, array $sobrescribir = [], array $cabeceras = [])
    {
        $quote->load('items');
        $datos = array_merge([
            'nombre' => 'Ana Ruiz',
            'acepto' => '1',
            'hash'   => $quote->snapshotHash(),
        ], $sobrescribir);

        return $this->withHeaders(array_merge(['User-Agent' => 'SeguimientoTest/1.0'], $cabeceras))
            ->post(route('tracking.quote.accept', ['token' => $order->tracking_token, 'quote' => $quote->id]), $datos);
    }

    public function test_cliente_acepta_cotizacion_enviada_con_hash_correcto(): void
    {
        $order = $this->orden();
        $quote = $this->enviada($order);

        $this->aceptar($order, $quote)->assertSessionHasNoErrors();

        $this->assertSame(Quote::APROBADA, $quote->fresh()->status);
    }

    public function test_aceptacion_guarda_nombre_fecha_ip_user_agent_y_hash(): void
    {
        $order = $this->orden();
        $quote = $this->enviada($order);
        $hash = $quote->load('items')->snapshotHash();

        $this->aceptar($order, $quote, ['hash' => $hash])->assertSessionHasNoErrors();

        $fresh = $quote->fresh();
        $this->assertSame('enlace', $fresh->approval_method);
        $this->assertSame('Ana Ruiz', $fresh->client_accepted_name);
        $this->assertNotNull($fresh->client_accepted_at);
        $this->assertNotNull($fresh->client_accepted_ip);
        $this->assertSame('SeguimientoTest/1.0', $fresh->client_accepted_agent);
        $this->assertSame($hash, $fresh->client_acceptance_hash);
    }

    public function test_aceptacion_autoriza_los_conceptos_segun_a2_y_sincroniza_el_estado_financiero(): void
    {
        $order = $this->orden();
        $quote = $this->enviada($order);

        $antes = app(EstadoFinancieroAction::class)->execute($order->fresh());
        $this->assertGreaterThan(0, (float) $antes['pendiente_autorizacion']);

        $this->aceptar($order, $quote)->assertSessionHasNoErrors();

        $order->items()->each(fn (RepairOrderItem $item) => $this->assertSame($quote->id, (int) $item->fresh()->billed_quote_id));

        $despues = app(EstadoFinancieroAction::class)->execute($order->fresh());
        $this->assertSame(0.0, (float) $despues['pendiente_autorizacion']);
    }

    public function test_hash_alterado_se_rechaza_y_la_cotizacion_sigue_enviada(): void
    {
        $order = $this->orden();
        $quote = $this->enviada($order);

        $this->aceptar($order, $quote, ['hash' => str_repeat('a', 64)])->assertSessionHasErrors('quote');

        $this->assertSame(Quote::ENVIADA, $quote->fresh()->status);
    }

    public function test_la_casilla_de_aceptacion_es_obligatoria(): void
    {
        $order = $this->orden();
        $quote = $this->enviada($order);

        $this->aceptar($order, $quote, ['acepto' => null])->assertSessionHasErrors('acepto');

        $this->assertSame(Quote::ENVIADA, $quote->fresh()->status);
    }

    public function test_nombre_insuficiente_no_acepta(): void
    {
        $order = $this->orden();
        $quote = $this->enviada($order);

        $this->aceptar($order, $quote, ['nombre' => 'Ana'])->assertSessionHasErrors('nombre');

        $this->assertSame(Quote::ENVIADA, $quote->fresh()->status);
    }

    public function test_cotizacion_borrador_no_puede_aceptarse(): void
    {
        $order = $this->orden();
        $borrador = app(CreateQuoteAction::class)->execute($order);

        $this->aceptar($order, $borrador)->assertNotFound();

        $this->assertSame(Quote::BORRADOR, $borrador->fresh()->status);
    }

    public function test_cotizacion_ya_aprobada_no_vuelve_a_aceptarse(): void
    {
        $order = $this->orden();
        $quote = $this->enviada($order);
        $quote->approve('enlace', 'Ana Ruiz');

        $this->aceptar($order, $quote->fresh())->assertSessionHasErrors('quote');

        $this->assertSame(Quote::APROBADA, $quote->fresh()->status);
    }

    public function test_orden_cerrada_no_admite_aceptacion(): void
    {
        $order = $this->orden('entregado');
        $quote = $this->enviada($order);

        $this->aceptar($order, $quote)->assertSessionHasErrors('quote');

        $this->assertSame(Quote::ENVIADA, $quote->fresh()->status);
    }

    public function test_cliente_rechaza_cotizacion_enviada_y_conserva_el_motivo(): void
    {
        $order = $this->orden();
        $quote = $this->enviada($order);

        $this->post(route('tracking.quote.reject', ['token' => $order->tracking_token, 'quote' => $quote->id]), [
            'motivo' => 'Lo voy a resolver en otro taller',
        ])->assertSessionHasNoErrors();

        $fresh = $quote->fresh();
        $this->assertSame(Quote::RECHAZADA, $fresh->status);
        $this->assertNotNull($fresh->rejected_at);
        $this->assertSame('Lo voy a resolver en otro taller', $fresh->rejection_reason);
    }

    public function test_rechazo_sin_motivo_es_valido_y_deja_el_motivo_vacio(): void
    {
        $order = $this->orden();
        $quote = $this->enviada($order);

        $this->post(route('tracking.quote.reject', ['token' => $order->tracking_token, 'quote' => $quote->id]), [])
            ->assertSessionHasNoErrors();

        $this->assertSame(Quote::RECHAZADA, $quote->fresh()->status);
        $this->assertNull($quote->fresh()->rejection_reason);
    }

    public function test_seguimiento_publico_no_muestra_borradores(): void
    {
        $order = $this->orden();
        app(CreateQuoteAction::class)->execute($order); // borrador privado del taller
        $enviada = $this->enviada($order);

        $pagina = $this->get(route('tracking.show', ['token' => $order->tracking_token]))
            ->assertOk()
            ->viewData('page');

        $estados = collect($pagina['props']['quotes'])->pluck('status')->all();
        $this->assertNotContains(Quote::BORRADOR, $estados);
        $this->assertContains(Quote::ENVIADA, $estados);
        $this->assertSame($enviada->id, collect($pagina['props']['quotes'])->firstWhere('status', Quote::ENVIADA)['id']);
    }
}
