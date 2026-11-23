<?php

namespace Tests\Feature;

use App\Actions\Quotes\CreateQuoteAction;
use App\Models\Client;
use App\Models\FollowUp;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\Part;
use App\Models\Quote;
use App\Models\RepairOrderItem;
use App\Models\MechanicType;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * IMPLEMENTACIÓN-UI-4B (cierre de brecha backend): una orden cerrada (entregado/rechazado)
 * es de solo lectura para TODOS los roles en diagnóstico y en bitácora.
 */
class ClosedOrderGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Mechanic} */
    private function mechanic(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $mechanic = Mechanic::create([
            'name'             => 'Mecánico cierre',
            'user_id'          => $user->id,
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        return [$user, $mechanic];
    }

    private function estado(string $slug): RepairOrderStatus
    {
        return RepairOrderStatus::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'color_class' => 'bg-gray-500'],
        );
    }

    private function orden(string $slug, ?Mechanic $mechanic, bool $needsQuote = false): RepairOrder
    {
        $client = Client::factory()->create(['rfc' => 'XAXX010101000']);
        $recepcion = Recepcion::factory()->create(['client_id' => $client->id]);

        return RepairOrder::factory()->create([
            'recepcion_id' => $recepcion->id,
            'client_id'    => $client->id,
            'vehicle_id'   => $recepcion->vehicle_id,
            'mechanic_id'  => $mechanic?->id,
            'status_id'    => $this->estado($slug)->id,
            'needs_quote'  => $needsQuote,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    // ── Diagnóstico: orden cerrada bloqueada para todos los roles ─────────

    public function test_admin_no_puede_registrar_diagnostico_en_orden_entregada(): void
    {
        [, $mec] = $this->mechanic();
        $orden = $this->orden('entregado', $mec);

        $this->actingAs($this->admin())
            ->post(route('repair-orders.diagnosis.complete', $orden))
            ->assertForbidden();

        $orden->refresh();
        $this->assertFalse((bool) $orden->needs_quote);
        $this->assertSame('entregado', $orden->status->slug);
        $this->assertSame(0, FollowUp::count());
    }

    public function test_admin_no_puede_registrar_diagnostico_en_orden_rechazada_ni_genera_nota(): void
    {
        $orden = $this->orden('rechazado', null);

        $this->actingAs($this->admin())
            ->post(route('repair-orders.diagnosis.complete', $orden))
            ->assertForbidden();

        $orden->refresh();
        $this->assertFalse((bool) $orden->needs_quote);
        $this->assertSame('rechazado', $orden->status->slug);
        $this->assertSame(0, FollowUp::count());
    }

    public function test_mecanico_asignado_no_puede_registrar_diagnostico_en_orden_entregada(): void
    {
        [$user, $mec] = $this->mechanic();
        $orden = $this->orden('entregado', $mec);

        $this->actingAs($user)
            ->post(route('repair-orders.diagnosis.complete', $orden))
            ->assertForbidden();

        $orden->refresh();
        $this->assertFalse((bool) $orden->needs_quote);
        $this->assertSame('entregado', $orden->status->slug);
        $this->assertSame(0, FollowUp::count());
    }

    public function test_diagnostico_no_modifica_needs_quote_ya_existente_en_orden_cerrada(): void
    {
        [$user, $mec] = $this->mechanic();
        $orden = $this->orden('entregado', $mec, needsQuote: true);

        $this->actingAs($user)
            ->post(route('repair-orders.diagnosis.complete', $orden))
            ->assertForbidden();

        $this->assertTrue((bool) $orden->refresh()->needs_quote);
        $this->assertSame(0, FollowUp::count());
    }

    public function test_orden_abierta_conserva_el_diagnostico_del_mecanico(): void
    {
        [$user, $mec] = $this->mechanic();
        $this->estado('diagnostico'); // destino del cambio de estado; las migraciones no lo siembran
        $orden = $this->orden('recibido', $mec);

        $this->actingAs($user)
            ->post(route('repair-orders.diagnosis.complete', $orden))
            ->assertRedirect();

        $orden->refresh();
        $this->assertTrue((bool) $orden->needs_quote);
        $this->assertSame('diagnostico', $orden->status->slug);
        $this->assertSame(1, FollowUp::count());
    }

    // ── Bitácora: orden cerrada sin notas nuevas para todos los roles ─────

    public function test_admin_no_puede_agregar_nota_en_orden_entregada(): void
    {
        [, $mec] = $this->mechanic();
        $orden = $this->orden('entregado', $mec);

        $this->actingAs($this->admin())
            ->post(route('repair-orders.follow-ups.store', $orden), [
                'mechanic_id' => $mec->id,
                'description' => 'Nota tardía',
                'date'        => '2026-11-22',
            ])
            ->assertForbidden();

        $this->assertSame(0, FollowUp::count());
    }

    public function test_admin_no_puede_agregar_nota_en_orden_rechazada(): void
    {
        $orden = $this->orden('rechazado', null);

        $this->actingAs($this->admin())
            ->post(route('repair-orders.follow-ups.store', $orden), [
                'mechanic_id' => null,
                'description' => 'Nota tardía',
                'date'        => '2026-11-22',
            ])
            ->assertForbidden();

        $this->assertSame(0, FollowUp::count());
    }

    public function test_mecanico_asignado_no_puede_agregar_nota_en_orden_entregada(): void
    {
        [$user, $mec] = $this->mechanic();
        $orden = $this->orden('entregado', $mec);

        $this->actingAs($user)
            ->post(route('repair-orders.follow-ups.store', $orden), [
                'description' => 'Nota tardía',
                'date'        => '2026-11-22',
            ])
            ->assertForbidden();

        $this->assertSame(0, FollowUp::count());
    }

    public function test_orden_abierta_sigue_aceptando_notas(): void
    {
        [$user, $mec] = $this->mechanic();
        $orden = $this->orden('en-reparacion', $mec);

        $this->actingAs($user)
            ->post(route('repair-orders.follow-ups.store', $orden), [
                'description' => 'Avance real',
                'date'        => '2026-11-22',
            ])
            ->assertRedirect();

        $this->assertSame(1, FollowUp::count());
    }

    /** @return array<string, array{0: string}> */
    public static function estadosCerrados(): array
    {
        return [
            'entregado' => ['entregado'],
            'rechazado' => ['rechazado'],
        ];
    }

    #[DataProvider('estadosCerrados')]
    public function test_nadie_asigna_ni_reasigna_mecanico_en_orden_cerrada(string $slug): void
    {
        [, $actual] = $this->mechanic();
        [, $otro] = $this->mechanic();
        $orden = $this->orden($slug, $actual);

        $this->actingAs($this->admin())
            ->patch(route('repair-orders.mechanic.assign', $orden), ['mechanic_id' => $otro->id])
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->post(route('repair-orders.mechanic.reassign', $orden), [
                'mechanic_id' => $otro->id,
                'reason'      => 'Cambio de responsable',
            ])
            ->assertForbidden();

        $this->assertSame($actual->id, (int) $orden->refresh()->mechanic_id);
        $this->assertSame(0, FollowUp::count());
    }

    #[DataProvider('estadosCerrados')]
    public function test_nadie_elimina_notas_de_orden_cerrada_y_la_nota_permanece(string $slug): void
    {
        [$user, $mec] = $this->mechanic();
        $orden = $this->orden($slug, $mec);
        $nota = FollowUp::create([
            'repair_order_id' => $orden->id,
            'mechanic_id'     => $mec->id,
            'description'     => 'Histórica',
            'date'            => '2026-11-22',
        ]);

        $this->actingAs($this->admin())
            ->delete(route('repair-orders.follow-ups.destroy', [$orden, $nota]))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('repair-orders.follow-ups.destroy', [$orden, $nota]))
            ->assertForbidden();

        $this->assertTrue(FollowUp::whereKey($nota->id)->exists());
    }

    public function test_admin_asigna_y_reasigna_mecanico_en_orden_abierta(): void
    {
        [, $mec] = $this->mechanic();
        [, $otro] = $this->mechanic();
        $orden = $this->orden('en-reparacion', $mec);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('repair-orders.mechanic.assign', $orden), ['mechanic_id' => $otro->id])
            ->assertRedirect();
        $this->assertSame($otro->id, (int) $orden->refresh()->mechanic_id);

        $this->actingAs($admin)
            ->post(route('repair-orders.mechanic.reassign', $orden), [
                'mechanic_id' => $mec->id,
                'reason'      => 'Vuelve al taller',
            ])
            ->assertRedirect();
        $this->assertSame($mec->id, (int) $orden->refresh()->mechanic_id);
        $this->assertSame(1, FollowUp::count());
    }

    private function parte(): Part
    {
        return Part::create([
            'name'                => 'Balata delantera',
            'sku'                 => 'BAL-' . uniqid(),
            'cost_price'          => 60,
            'sale_price'          => 80,
            'stock'               => 5,
            'low_stock_threshold' => 1,
        ]);
    }

    private function concepto(RepairOrder $order, string $tipo, float $importe): RepairOrderItem
    {
        return RepairOrderItem::create([
            'repair_order_id' => $order->id, 'type' => $tipo, 'description' => 'Concepto',
            'quantity' => 1, 'unit_price' => $importe, 'subtotal' => $importe,
        ]);
    }

    #[DataProvider('estadosCerrados')]
    public function test_nadie_agrega_ni_quita_conceptos_en_orden_cerrada(string $slug): void
    {
        [$user, $mec] = $this->mechanic();
        $orden = $this->orden($slug, $mec);
        $mano = $this->concepto($orden, 'labor', 500);
        $refaccion = $this->concepto($orden, 'part', 200);
        $parte = $this->parte();

        $this->actingAs($this->admin())
            ->post(route('repair-orders.items.store', $orden), [
                'type' => 'labor', 'description' => 'Extra', 'quantity' => 1, 'unit_price' => 100,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('repair-orders.items.store', $orden), [
                'type' => 'part', 'part_id' => $parte->id, 'quantity' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->delete(route('repair-orders.items.destroy', [$orden, $mano]))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('repair-orders.items.destroy', [$orden, $refaccion]))
            ->assertForbidden();

        $this->assertSame(2, RepairOrderItem::count());
        $this->assertSame(5, $parte->fresh()->stock);
    }

    public function test_mecanico_no_recibe_importes_ni_id_de_cotizacion_en_conceptos(): void
    {
        [$user, $mec] = $this->mechanic();
        $orden = $this->orden('en-reparacion', $mec);
        $this->concepto($orden, 'part', 300);

        $this->actingAs($user)
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('orden.items', 1)
                ->missing('orden.items.0.unit_price')
                ->missing('orden.items.0.subtotal')
                ->missing('orden.items.0.billed_quote_id')
                ->where('orden.items.0.autorizado', false));
    }

    public function test_concepto_facturado_se_marca_autorizado_sin_exponer_cotizacion_a_mecanico(): void
    {
        [$user, $mec] = $this->mechanic();
        $orden = $this->orden('en-reparacion', $mec);
        $item = $this->concepto($orden, 'part', 300);

        $quote = app(CreateQuoteAction::class)->execute($orden, null, Quote::KIND_PRINCIPAL);
        $quote->update(['total' => 300]);
        $quote->approve('presencial', 'Cliente de prueba');
        $this->assertNotNull($item->fresh()->billed_quote_id);

        $this->actingAs($user)
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('orden.items.0.autorizado', true)
                ->missing('orden.items.0.billed_quote_id'));

        $this->actingAs($this->admin())
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('orden.items.0.autorizado', true)
                ->missing('orden.items.0.billed_quote_id'));
    }

    public function test_catalogo_de_refacciones_no_expone_precio_de_venta_a_mecanico(): void
    {
        [$user, $mec] = $this->mechanic();
        $orden = $this->orden('en-reparacion', $mec);
        $this->parte();

        $this->actingAs($user)
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('parts', 1)
                ->missing('parts.0.sale_price'));

        $this->actingAs($this->admin())
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('parts.0.sale_price'));
    }

    private function cotizacion(RepairOrder $orden, string $estado, string $kind = Quote::KIND_PRINCIPAL): Quote
    {
        if ($orden->items()->count() === 0) {
            $this->concepto($orden, 'labor', 500);
        }

        $quote = app(CreateQuoteAction::class)->execute($orden, null, $kind);
        if ($estado !== Quote::BORRADOR) {
            $quote->update(['status' => $estado]);
        }

        return $quote->fresh();
    }

    #[DataProvider('estadosCerrados')]
    public function test_nadie_crea_cotizaciones_en_orden_cerrada(string $slug): void
    {
        [, $mec] = $this->mechanic();
        $orden = $this->orden($slug, $mec);
        $this->concepto($orden, 'labor', 500);

        $this->actingAs($this->admin())
            ->post(route('repair-orders.quotes.store', $orden), ['kind' => Quote::KIND_PRINCIPAL])
            ->assertForbidden();

        $this->assertSame(0, Quote::count());
    }

    #[DataProvider('estadosCerrados')]
    public function test_nadie_envia_aprueba_rechaza_ni_elimina_cotizaciones_en_orden_cerrada(string $slug): void
    {
        [, $mec] = $this->mechanic();
        $orden = $this->orden($slug, $mec);
        $enviada = $this->cotizacion($orden, Quote::ENVIADA);
        $borrador = $this->cotizacion($orden, Quote::BORRADOR, Quote::KIND_ADICIONAL);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('repair-orders.quotes.send', [$orden, $borrador]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('repair-orders.quotes.destroy', [$orden, $borrador]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('repair-orders.quotes.approve', [$orden, $enviada]), [
                'approval_method' => 'presencial', 'approved_by_name' => 'Cliente de prueba',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('repair-orders.quotes.reject', [$orden, $enviada]), ['rejection_reason' => 'No'])
            ->assertForbidden();

        $this->assertSame(Quote::ENVIADA, $enviada->fresh()->status);
        $this->assertSame(Quote::BORRADOR, $borrador->fresh()->status);
        $this->assertSame(2, Quote::count());
    }

    #[DataProvider('estadosCerrados')]
    public function test_cliente_no_acepta_ni_rechaza_cotizacion_de_orden_cerrada(string $slug): void
    {
        [, $mec] = $this->mechanic();
        $orden = $this->orden($slug, $mec);
        $enviada = $this->cotizacion($orden, Quote::ENVIADA);
        $token = $orden->fresh()->tracking_token;

        $this->post(route('tracking.quote.accept', [$token, $enviada]), [
            'nombre' => 'Cliente de prueba', 'acepto' => '1', 'hash' => str_repeat('a', 64),
        ])->assertSessionHasErrors('quote');

        $this->post(route('tracking.quote.reject', [$token, $enviada]), ['motivo' => 'No'])
            ->assertSessionHasErrors('quote');

        $enviada->refresh();
        $this->assertSame(Quote::ENVIADA, $enviada->status);
        $this->assertNull($enviada->client_accepted_at);
        $this->assertNull($enviada->approved_at);
    }

    #[DataProvider('estadosCerrados')]
    public function test_tracking_de_orden_cerrada_no_invita_a_aprobar_ni_ofrece_acciones(string $slug): void
    {
        [, $mec] = $this->mechanic();
        $orden = $this->orden($slug, $mec);
        $this->cotizacion($orden, Quote::ENVIADA);

        $this->get(route('tracking.show', ['token' => $orden->fresh()->tracking_token]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('orden.cerrada', true)
                ->where('quotes.0.status', Quote::ENVIADA)
                ->where('quotes.0.can_accept', false)
                ->where('pendientes', [])
            );
    }

    public function test_tracking_de_orden_abierta_con_cotizacion_enviada_invita_a_aprobar(): void
    {
        [, $mec] = $this->mechanic();
        $orden = $this->orden('en-reparacion', $mec);
        $quote = $this->cotizacion($orden, Quote::ENVIADA);

        $this->get(route('tracking.show', ['token' => $orden->fresh()->tracking_token]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('orden.cerrada', false)
                ->where('quotes.0.can_accept', true)
                ->where('pendientes', [[
                    'texto' => 'Revisa y aprueba la cotización ' . $quote->folio . ' para que el taller continúe.',
                ]])
            );
    }
}
