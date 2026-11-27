<?php

namespace Tests\Feature;

use App\Actions\Quotes\CreateQuoteAction;
use App\Actions\RepairOrders\EstadosFinancierosOrdenesAction;
use App\Models\Client;
use App\Models\Mechanic;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reportes V2: dashboard por rango. Ingresos = pagos reales (FIN-002); autorizado y saldo = A2.
 * Solo owner/admin. El PDF mensual se conserva.
 */
class ReportsV2Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function orden(string $slug = 'recibido', ?Mechanic $mecanico = null): RepairOrder
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
            'recepcion_id' => $recepcion->id,
            'tax_rate' => 16,
            'status_id' => $status->id,
            'mechanic_id' => $mecanico?->id,
        ]);
    }

    private function concepto(RepairOrder $orden, float $subtotal, string $tipo = 'labor'): void
    {
        RepairOrderItem::create([
            'repair_order_id' => $orden->id, 'type' => $tipo, 'description' => 'Trabajo',
            'quantity' => 1, 'unit_price' => $subtotal, 'subtotal' => $subtotal,
        ]);
    }

    /** Cotización aprobada con importe autorizado fijo (snapshot A2). */
    private function autorizar(RepairOrder $orden, float $monto): Quote
    {
        $quote = app(CreateQuoteAction::class)->execute($orden, null, Quote::KIND_PRINCIPAL);
        $quote->update(['total' => $monto]);
        $quote->approve('presencial', 'Cliente de prueba');

        return $quote->fresh();
    }

    /** Pago con fecha de registro controlada (created_at), como usa el reporte. */
    private function pago(RepairOrder $orden, float $monto, Carbon $fecha, ?Quote $cotizacion = null): Payment
    {
        $pago = $orden->payments()->create([
            'amount' => $monto,
            'payment_method' => 'Cash',
            'quote_id' => $cotizacion?->id,
        ]);
        $pago->forceFill(['created_at' => $fecha])->saveQuietly();

        return $pago;
    }

    private function reporte(array $query = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->get(route('reports.index', $query + [
            'desde' => '2026-11-01',
            'hasta' => '2026-11-30',
        ]));
    }

    // ── Seguridad y acceso ───────────────────────────────────────────────

    public function test_owner_accede_a_reportes(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Reports/Index'));
    }

    public function test_admin_accede_a_reportes(): void
    {
        $this->reporte()->assertOk()->assertInertia(fn ($page) => $page->component('Reports/Index'));
    }

    public function test_mecanico_recibe_403(): void
    {
        $mecanico = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $this->actingAs($mecanico)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($mecanico)->get(route('reports.pdf', ['month' => 11, 'year' => 2026]))->assertForbidden();
    }

    public function test_invitado_redirige_a_login(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->get(route('reports.pdf', ['month' => 11, 'year' => 2026]))->assertRedirect(route('login'));
    }

    // ── Indicadores y fuentes ────────────────────────────────────────────

    public function test_ingresos_usan_pagos_reales_del_periodo(): void
    {
        $orden = $this->orden('reparado');
        $this->concepto($orden, 1000);
        $cotizacion = $this->autorizar($orden, 1000);
        $this->pago($orden, 300, Carbon::create(2026, 11, 10, 12), $cotizacion);
        $this->pago($orden, 200, Carbon::create(2026, 11, 20, 12), $cotizacion);

        $this->reporte()->assertInertia(fn ($page) => $page
            ->where('reporte.indicadores.ingresos_cobrados', 500)
            ->where('reporte.indicadores.pagos', 2)
            ->where('reporte.indicadores.trabajo_autorizado', 1000)
        );
    }

    public function test_cotizacion_aprobada_sin_pago_no_genera_ingreso(): void
    {
        $orden = $this->orden('reparado');
        $this->concepto($orden, 1000);
        $this->autorizar($orden, 1000);

        $this->reporte()->assertInertia(fn ($page) => $page
            ->where('reporte.indicadores.ingresos_cobrados', 0)
            ->where('reporte.indicadores.trabajo_autorizado', 1000)
        );
    }

    public function test_anticipo_general_sin_cotizacion_cuenta_como_ingreso(): void
    {
        $orden = $this->orden();
        $this->pago($orden, 400, Carbon::create(2026, 11, 5, 12), null);

        $this->reporte()->assertInertia(fn ($page) => $page
            ->where('reporte.indicadores.ingresos_cobrados', 400)
            ->where('reporte.desglose.total_cobrado', 400)
        );
    }

    public function test_pago_eliminado_no_cuenta(): void
    {
        $orden = $this->orden();
        $this->pago($orden, 300, Carbon::create(2026, 11, 5, 12));
        $borrado = $this->pago($orden, 150, Carbon::create(2026, 11, 6, 12));
        $borrado->delete();

        $this->reporte()->assertInertia(fn ($page) => $page
            ->where('reporte.indicadores.ingresos_cobrados', 300)
            ->where('reporte.indicadores.pagos', 1)
        );
    }

    public function test_rango_de_fechas_se_respeta(): void
    {
        $orden = $this->orden();
        $this->pago($orden, 100, Carbon::create(2026, 10, 31, 23), null);
        $this->pago($orden, 250, Carbon::create(2026, 11, 15, 12), null);
        $this->pago($orden, 900, Carbon::create(2026, 12, 1, 0), null);

        $this->reporte()->assertInertia(fn ($page) => $page
            ->where('reporte.indicadores.ingresos_cobrados', 250)
        );
    }

    public function test_autorizado_y_saldo_coinciden_con_a2(): void
    {
        $orden = $this->orden('reparado');
        $orden->forceFill(['created_at' => Carbon::create(2026, 11, 5, 9)])->saveQuietly();
        $this->concepto($orden, 1000, 'part');
        $cotizacion = $this->autorizar($orden, 1160);
        $this->pago($orden, 300, Carbon::create(2026, 11, 10, 12), $cotizacion);

        $a2 = app(EstadosFinancierosOrdenesAction::class)->execute(collect([$orden->fresh()]))[$orden->id];

        $props = $this->reporte()->inertiaProps('reporte');
        $fila = $props['ordenes'][0];

        // Comparación numérica: Inertia serializa 1160 o 1160.0 según el valor; el importe es el mismo.
        $this->assertEqualsWithDelta((float) $a2['total_autorizado'], (float) $props['desglose']['total_autorizado'], 0.005, 'desglose.total_autorizado vs A2');
        $this->assertEqualsWithDelta((float) $a2['total_autorizado'], (float) $fila['total_autorizado'], 0.005, 'ordenes.total_autorizado vs A2');
        $this->assertEqualsWithDelta((float) $a2['total_pagado'], (float) $fila['pagado'], 0.005, 'ordenes.pagado vs A2');
        $this->assertEqualsWithDelta((float) $a2['saldo_autorizado'], (float) $fila['saldo'], 0.005, 'ordenes.saldo vs A2');
    }

    public function test_saldo_nunca_es_negativo(): void
    {
        $orden = $this->orden('reparado');
        $this->concepto($orden, 500);
        $cotizacion = $this->autorizar($orden, 500);
        $this->pago($orden, 800, Carbon::create(2026, 11, 10, 12), $cotizacion);

        $this->reporte()->assertInertia(fn ($page) => $page
            ->where('reporte.ordenes.0.saldo', 0)
            ->where('reporte.desglose.saldo_pendiente', 0)
        );
    }

    public function test_tabla_de_pagos_suma_exactamente_el_ingreso(): void
    {
        $orden = $this->orden();
        $this->pago($orden, 120.50, Carbon::create(2026, 11, 3, 10), null);
        $this->pago($orden, 79.50, Carbon::create(2026, 11, 4, 10), null);

        $respuesta = $this->reporte();
        $props = $respuesta->inertiaProps('reporte');
        $suma = round(array_sum(array_column($props['pagos'], 'monto')), 2);

        $this->assertSame((float) $props['indicadores']['ingresos_cobrados'], $suma);
        $this->assertSame(200.0, $suma);
    }

    public function test_productividad_mecanicos_usa_la_fuente_existente(): void
    {
        $mecanico = Mechanic::factory()->create();
        $orden = $this->orden('entregado', $mecanico);
        $this->concepto($orden, 700);

        $admin = $this->admin();
        $existente = $this->actingAs($admin)->get(route('reports.mechanics', ['month' => 11, 'year' => 2026]))
            ->assertOk()
            ->inertiaProps();

        $this->assertArrayHasKey('rows', $existente);
        $this->assertArrayHasKey('totals', $existente);
    }

    public function test_pdf_mensual_responde_pdf(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.pdf', ['month' => 11, 'year' => 2026, 'lang' => 'es']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    // ── Validación ───────────────────────────────────────────────────────

    public function test_fechas_invalidas_se_rechazan(): void
    {
        $this->reporte(['desde' => '2026-13-01', 'hasta' => '2026-13-31'])
            ->assertSessionHasErrors(['desde', 'hasta']);

        $this->reporte(['desde' => '2026-11-10', 'hasta' => '2026-11-01'])
            ->assertSessionHasErrors(['hasta']);
    }

    public function test_filtros_con_ids_inexistentes_se_rechazan(): void
    {
        $this->reporte(['estado_id' => 999999, 'mecanico_id' => 999999])
            ->assertSessionHasErrors(['estado_id', 'mecanico_id']);
    }

    public function test_reparadas_y_entregadas_son_estado_actual_de_ordenes_creadas_en_rango(): void
    {
        $reparada = $this->orden('reparado');
        $reparada->forceFill(['created_at' => Carbon::create(2026, 11, 5, 9)])->saveQuietly();

        $fueraDeRango = $this->orden('reparado');
        $fueraDeRango->forceFill(['created_at' => Carbon::create(2026, 10, 5, 9)])->saveQuietly();

        $this->reporte()->assertInertia(fn ($page) => $page
            ->where('reporte.indicadores.ordenes_creadas', 1)
            ->where('reporte.indicadores.ordenes_reparadas_actual', 1)
            ->where('reporte.indicadores.ordenes_entregadas_actual', 0)
        );
    }
}
