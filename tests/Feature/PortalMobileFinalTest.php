<?php

namespace Tests\Feature;

use App\Actions\RepairOrders\EstadoFinancieroAction;
use App\Models\AppointmentRequest;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Portal móvil final: regeneración del enlace, clasificación de cotizaciones y orden finalizada. */
class PortalMobileFinalTest extends TestCase
{
    use RefreshDatabase;

    private int $contadorCotizaciones = 0;

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role' => $rol]);
    }

    private function orden(string $slug = 'diagnostico'): RepairOrder
    {
        $status = RepairOrderStatus::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'color_class' => 'bg-gray-500']
        );
        $cliente = Client::factory()->create();
        $vehiculo = Vehicle::factory()->create(['client_id' => $cliente->id]);
        $recepcion = Recepcion::create([
            'client_id' => $cliente->id, 'vehicle_id' => $vehiculo->id, 'fuel_level' => '1/2',
        ]);

        return RepairOrder::factory()->create([
            'recepcion_id' => $recepcion->id, 'tax_rate' => 16, 'status_id' => $status->id,
        ]);
    }

    private function cotizacion(RepairOrder $orden, string $status, string $kind = Quote::KIND_PRINCIPAL, array $extra = []): Quote
    {
        $n = ++$this->contadorCotizaciones;
        $quote = Quote::create(array_merge([
            'repair_order_id' => $orden->id,
            'version' => $n,
            'folio' => 'Q-T-' . $n . '-' . $orden->id,
            'kind' => $kind,
            'status' => $status,
            'tax_rate' => 16,
            'parts_subtotal' => 100,
            'labor_subtotal' => 200,
            'subtotal' => 300,
            'tax' => 48,
            'total' => 348,
            'valid_until' => now()->addDays(10)->toDateString(),
        ], $extra));

        QuoteItem::create([
            'quote_id' => $quote->id, 'type' => 'part', 'description' => 'Balata',
            'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100,
        ]);

        return $quote;
    }

    /** Consultas SQL que tocan quote_items durante una petición de seguimiento. */
    private function consultasDeItems(string $token): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('tracking.show', $token))->assertOk();
        $consultas = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], 'quote_items'))->count();
        DB::disableQueryLog();

        return $consultas;
    }

    // ── 1. Regeneración del enlace ────────────────────────────────────────

    public function test_admin_regenera_el_enlace_y_el_anterior_deja_de_funcionar(): void
    {
        $orden = $this->orden();
        $antiguo = $orden->tracking_token;

        $this->actingAs($this->usuario(User::ROLE_ADMIN))
            ->post(route('repair-orders.tracking.regenerate', $orden))
            ->assertRedirect();

        $nuevo = $orden->fresh()->tracking_token;
        $this->assertNotSame($antiguo, $nuevo);
        $this->assertSame(48, strlen($nuevo));

        $this->get(route('tracking.show', $antiguo))->assertNotFound();
        $this->get(route('tracking.show', $nuevo))->assertOk();
    }

    public function test_owner_puede_regenerar_el_enlace(): void
    {
        $orden = $this->orden();
        $antiguo = $orden->tracking_token;

        $this->actingAs($this->usuario(User::ROLE_OWNER))
            ->post(route('repair-orders.tracking.regenerate', $orden))
            ->assertRedirect();

        $this->assertNotSame($antiguo, $orden->fresh()->tracking_token);
    }

    public function test_mecanico_no_puede_regenerar_el_enlace(): void
    {
        $orden = $this->orden();
        $antiguo = $orden->tracking_token;

        $this->actingAs($this->usuario(User::ROLE_MECHANIC))
            ->post(route('repair-orders.tracking.regenerate', $orden))
            ->assertForbidden();

        $this->assertSame($antiguo, $orden->fresh()->tracking_token);
    }

    public function test_invitado_es_enviado_a_login_al_regenerar(): void
    {
        $orden = $this->orden();
        $antiguo = $orden->tracking_token;

        $this->post(route('repair-orders.tracking.regenerate', $orden))->assertRedirectContains('login');

        $this->assertSame($antiguo, $orden->fresh()->tracking_token);
    }

    public function test_regenerar_no_modifica_estado_finanzas_ni_cotizaciones(): void
    {
        $orden = $this->orden();
        $this->cotizacion($orden, Quote::APROBADA);
        $antes = app(EstadoFinancieroAction::class)->execute($orden->fresh());
        $estadoAntes = $orden->status_id;
        $totalCotizacion = Quote::where('repair_order_id', $orden->id)->sum('total');

        $this->actingAs($this->usuario(User::ROLE_ADMIN))
            ->post(route('repair-orders.tracking.regenerate', $orden));

        $orden->refresh();
        $this->assertSame($estadoAntes, $orden->status_id);
        $this->assertEquals($antes, app(EstadoFinancieroAction::class)->execute($orden));
        $this->assertEquals($totalCotizacion, Quote::where('repair_order_id', $orden->id)->sum('total'));
    }

    // ── 2. Orden finalizada ───────────────────────────────────────────────

    public function test_orden_cerrada_sigue_resolviendo_seguimiento_con_cotizaciones_y_finanzas(): void
    {
        $orden = $this->orden('entregado');
        $this->cotizacion($orden, Quote::APROBADA);
        $financiero = app(EstadoFinancieroAction::class)->execute($orden->fresh());

        $respuesta = $this->get(route('tracking.show', $orden->tracking_token))->assertOk();

        $this->assertTrue($respuesta->inertiaProps('orden.cerrada'));
        $this->assertCount(1, $respuesta->inertiaProps('quotes'));
        $this->assertSame('visible', $respuesta->inertiaProps('quotes.0.bloque'));
        // Contrato público real: financiero = [total, pagado, saldo] (mapeado desde A2 en TrackingController::show).
        $this->assertEquals($financiero['total_autorizado'], $respuesta->inertiaProps('financiero.total'));
        $this->assertEquals($financiero['total_pagado'], $respuesta->inertiaProps('financiero.pagado'));
        $this->assertEquals($financiero['saldo_autorizado'], $respuesta->inertiaProps('financiero.saldo'));
    }

    public function test_orden_cerrada_no_expone_acciones_de_aceptar_en_cotizaciones(): void
    {
        $orden = $this->orden('entregado');
        $this->cotizacion($orden, Quote::ENVIADA);

        $respuesta = $this->get(route('tracking.show', $orden->tracking_token))->assertOk();

        $this->assertFalse($respuesta->inertiaProps('quotes.0.can_accept'));
    }

    public function test_nueva_cita_en_orden_cerrada_no_reabre_la_orden(): void
    {
        $orden = $this->orden('entregado');
        $estadoAntes = $orden->status_id;

        $this->post(route('tracking.appointment.store', $orden->tracking_token), [
            'reason' => 'revision',
            'preferred_date' => now()->addDays(5)->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseCount('appointment_requests', 1);
        $this->assertSame(AppointmentRequest::PENDIENTE, AppointmentRequest::firstOrFail()->status);
        $this->assertSame($estadoAntes, $orden->fresh()->status_id);
    }

    // ── 3. Clasificación de cotizaciones ──────────────────────────────────

    public function test_clasifica_cotizaciones_y_no_expone_borradores(): void
    {
        $orden = $this->orden();
        $this->cotizacion($orden, Quote::APROBADA, Quote::KIND_PRINCIPAL);
        $this->cotizacion($orden, Quote::APROBADA, Quote::KIND_ADICIONAL);
        $this->cotizacion($orden, Quote::ENVIADA);
        $vigente = $this->cotizacion($orden, Quote::ENVIADA);
        $this->cotizacion($orden, Quote::RECHAZADA);
        $this->cotizacion($orden, Quote::REEMPLAZADA);
        $this->cotizacion($orden, Quote::ENVIADA, Quote::KIND_PRINCIPAL, [
            'valid_until' => now()->subDay()->toDateString(),
        ]);
        $this->cotizacion($orden, Quote::BORRADOR);

        $cotizaciones = $this->get(route('tracking.show', $orden->tracking_token))
            ->assertOk()
            ->inertiaProps('quotes');

        $this->assertCount(7, $cotizaciones);
        $this->assertNotContains(Quote::BORRADOR, array_column($cotizaciones, 'status'));

        $visibles = array_values(array_filter($cotizaciones, fn (array $q) => $q['bloque'] === 'visible'));
        $historicas = array_values(array_filter($cotizaciones, fn (array $q) => $q['bloque'] === 'historico'));

        $this->assertCount(3, $visibles);
        $this->assertCount(4, $historicas);
        $this->assertSame($vigente->id, $visibles[0]['id']);
        $this->assertSame(
            // Prioridad visual: enviada vigente que requiere decisión primero, luego aprobadas.
            [Quote::ENVIADA, Quote::APROBADA, Quote::APROBADA],
            array_column($visibles, 'status')
        );
        foreach ($historicas as $h) {
            $this->assertNotSame($vigente->id, $h['id']);
        }
    }

    public function test_cotizacion_aprobada_no_se_etiqueta_como_historica_al_haber_enviada(): void
    {
        $orden = $this->orden();
        $aprobada = $this->cotizacion($orden, Quote::APROBADA);
        $this->cotizacion($orden, Quote::ENVIADA);

        $cotizaciones = $this->get(route('tracking.show', $orden->tracking_token))->inertiaProps('quotes');
        $fila = collect($cotizaciones)->firstWhere('id', $aprobada->id);

        $this->assertSame('visible', $fila['bloque']);
    }

    public function test_consultas_de_items_no_crecen_con_mas_cotizaciones(): void
    {
        $orden = $this->orden();
        $this->cotizacion($orden, Quote::APROBADA);
        $this->cotizacion($orden, Quote::REEMPLAZADA);
        $consultasBase = $this->consultasDeItems($orden->tracking_token);

        for ($i = 0; $i < 4; $i++) {
            $this->cotizacion($orden, Quote::RECHAZADA);
        }
        $consultasAmpliadas = $this->consultasDeItems($orden->tracking_token);

        $this->assertSame($consultasBase, $consultasAmpliadas, 'Los items de cotización deben cargarse en una sola consulta.');
    }

    public function test_a2_calcula_el_mismo_total_autorizado_con_la_clasificacion(): void
    {
        $orden = $this->orden();
        $this->cotizacion($orden, Quote::APROBADA);
        $this->cotizacion($orden, Quote::ENVIADA);
        $esperado = app(EstadoFinancieroAction::class)->execute($orden->fresh());

        $financiero = $this->get(route('tracking.show', $orden->tracking_token))
            ->inertiaProps('financiero');

        $this->assertEquals($esperado['total_autorizado'], $financiero['total']);
        $this->assertEquals($esperado['total_pagado'], $financiero['pagado']);
        $this->assertEquals($esperado['saldo_autorizado'], $financiero['saldo']);
    }
}
