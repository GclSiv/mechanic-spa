<?php

namespace Tests\Feature;

use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Actions\RepairOrders\EstadoFinancieroAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UI-10 · Fase 2: comisión fija por concepto de mano de obra, snapshot inmutable y convivencia con legacy.
 */
class LaborComisionFijaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function mecanico(): User
    {
        return User::factory()->create(['role' => User::ROLE_MECHANIC]);
    }

    private function mec(string $nombre, float $tasa = 0): Mechanic
    {
        return Mechanic::create([
            'name' => $nombre,
            'gender_id' => Gender::firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::firstOrCreate(['name' => 'General'])->id,
            'commission_rate' => $tasa,
        ]);
    }

    private function servicio(string $nombre, float $monto, bool $activo = true): Service
    {
        return Service::create(['name' => $nombre, 'commission_amount' => $monto, 'is_active' => $activo]);
    }

    private function ordenEn(string $slug, ?Mechanic $responsable = null, ?Carbon $fecha = null): RepairOrder
    {
        $status = RepairOrderStatus::firstOrCreate(['slug' => $slug], [
            'name' => ucfirst($slug), 'color_class' => 'bg-gray-500',
        ]);

        $orden = RepairOrder::factory()->create([
            'status_id' => $status->id,
            'mechanic_id' => $responsable?->id,
        ]);

        if ($fecha) {
            $orden->forceFill(['updated_at' => $fecha, 'created_at' => $fecha])->saveQuietly();
        }

        return $orden->refresh();
    }

    private function mano(RepairOrder $orden, array $datos): RepairOrderItem
    {
        return $this->crearConcepto(array_merge([
            'repair_order_id' => $orden->id, 'type' => 'labor', 'description' => 'Trabajo',
            'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0,
        ], $datos));
    }

    private function agregarLabor(User $actor, RepairOrder $orden, array $datos)
    {
        return $this->actingAs($actor)->post(route('repair-orders.items.store', $orden), array_merge([
            'type' => 'labor', 'description' => 'Afinación', 'quantity' => 1, 'unit_price' => 500,
        ], $datos));
    }


    /** Asignación interna para pruebas: commission_model y commission_amount_snapshot no son fillable. */
    private function crearConcepto(array $datos): RepairOrderItem
    {
        $item = new RepairOrderItem();
        $item->forceFill($datos)->save();

        return $item;
    }

    /** Props del reporte de productividad para el mes indicado. */
    private function reporte(Carbon $mes): array
    {
        return $this->actingAs($this->admin())
            ->get(route('reports.mechanics', ['month' => $mes->month, 'year' => $mes->year]))
            ->viewData('page')['props'];
    }

    private function fila(array $props, string $nombre): array
    {
        return collect($props['rows'])->firstWhere('name', $nombre);
    }

    // ── Reglas de alta ────────────────────────────────────────────────────

    public function test_mano_de_obra_nueva_exige_mecanico_ejecutor(): void
    {
        $orden = RepairOrder::factory()->create();

        $this->agregarLabor($this->admin(), $orden, [])->assertSessionHasErrors('mechanic_id');

        $this->assertSame(0, RepairOrderItem::where('repair_order_id', $orden->id)->count());
    }

    public function test_servicio_es_opcional(): void
    {
        $orden = RepairOrder::factory()->create();
        $mec = $this->mec('Ejecutor');

        $this->agregarLabor($this->admin(), $orden, ['mechanic_id' => $mec->id])->assertSessionHasNoErrors();

        $item = RepairOrderItem::where('repair_order_id', $orden->id)->firstOrFail();
        $this->assertNull($item->service_id);
    }

    public function test_con_servicio_guarda_fijo_con_snapshot_del_servicio(): void
    {
        $servicio = $this->servicio('Afinación 4 cilindros', 50);
        $orden = RepairOrder::factory()->create();
        $mec = $this->mec('Juan');

        $this->agregarLabor($this->admin(), $orden, [
            'mechanic_id' => $mec->id, 'service_id' => $servicio->id,
        ])->assertSessionHasNoErrors();

        $item = RepairOrderItem::where('repair_order_id', $orden->id)->firstOrFail();
        $this->assertSame(RepairOrderItem::MODELO_FIXED, $item->commission_model);
        $this->assertSame($servicio->id, $item->service_id);
        $this->assertSame($mec->id, $item->mechanic_id);
        $this->assertSame('50.00', (string) $item->commission_amount_snapshot);
    }

    public function test_texto_libre_guarda_fijo_sin_servicio_con_snapshot_cero(): void
    {
        $orden = RepairOrder::factory()->create();
        $mec = $this->mec('Pedro');

        $this->agregarLabor($this->admin(), $orden, ['mechanic_id' => $mec->id])->assertSessionHasNoErrors();

        $item = RepairOrderItem::where('repair_order_id', $orden->id)->firstOrFail();
        $this->assertSame(RepairOrderItem::MODELO_FIXED, $item->commission_model);
        $this->assertNull($item->service_id);
        $this->assertSame('0.00', (string) $item->commission_amount_snapshot);
    }

    public function test_servicio_inactivo_no_se_puede_elegir_en_concepto_nuevo(): void
    {
        $inactivo = $this->servicio('Retirado', 80, false);
        $orden = RepairOrder::factory()->create();

        $this->agregarLabor($this->admin(), $orden, [
            'mechanic_id' => $this->mec('X')->id, 'service_id' => $inactivo->id,
        ])->assertSessionHasErrors('service_id');

        $this->assertSame(0, RepairOrderItem::where('repair_order_id', $orden->id)->count());
    }

    public function test_horas_no_multiplican_la_comision(): void
    {
        $servicio = $this->servicio('Afinación 4 cilindros', 50);
        $orden = RepairOrder::factory()->create();

        $this->agregarLabor($this->admin(), $orden, [
            'mechanic_id' => $this->mec('Juan')->id, 'service_id' => $servicio->id,
            'quantity' => 3.5, 'unit_price' => 500,
        ])->assertSessionHasNoErrors();

        $item = RepairOrderItem::where('repair_order_id', $orden->id)->firstOrFail();
        $this->assertEquals(1750.0, (float) $item->subtotal);
        $this->assertSame('50.00', (string) $item->commission_amount_snapshot);
    }

    public function test_mecanico_ejecutor_puede_diferir_del_responsable_de_la_orden(): void
    {
        $responsable = $this->mec('Responsable');
        $ejecutor = $this->mec('Ejecutor');
        $orden = $this->ordenEn('en-reparacion', $responsable);

        $this->agregarLabor($this->admin(), $orden, ['mechanic_id' => $ejecutor->id])->assertSessionHasNoErrors();

        $item = RepairOrderItem::where('repair_order_id', $orden->id)->firstOrFail();
        $this->assertSame($ejecutor->id, $item->mechanic_id);
        $this->assertSame($responsable->id, $orden->fresh()->mechanic_id);
    }

    // ── Snapshot ──────────────────────────────────────────────────────────

    public function test_cambiar_el_servicio_no_altera_el_snapshot_existente(): void
    {
        $servicio = $this->servicio('Afinación 4 cilindros', 50);
        $orden = RepairOrder::factory()->create();
        $this->agregarLabor($this->admin(), $orden, [
            'mechanic_id' => $this->mec('Juan')->id, 'service_id' => $servicio->id,
        ]);

        $servicio->update(['name' => 'Renombrado', 'commission_amount' => 80]);

        $item = RepairOrderItem::where('repair_order_id', $orden->id)->firstOrFail();
        $this->assertSame('50.00', (string) $item->commission_amount_snapshot);
        $this->assertSame('Afinación', $item->description);
    }

    public function test_concepto_nuevo_usa_el_monto_actual_del_servicio(): void
    {
        $servicio = $this->servicio('Afinación 4 cilindros', 50);
        $servicio->update(['commission_amount' => 80]);
        $orden = RepairOrder::factory()->create();

        $this->agregarLabor($this->admin(), $orden, [
            'mechanic_id' => $this->mec('Juan')->id, 'service_id' => $servicio->id,
        ]);

        $this->assertSame('80.00', (string) RepairOrderItem::where('repair_order_id', $orden->id)->value('commission_amount_snapshot'));
    }

    // ── Permisos y estados ────────────────────────────────────────────────

    public function test_mecanico_no_puede_agregar_mano_de_obra(): void
    {
        $orden = RepairOrder::factory()->create();

        $this->agregarLabor($this->mecanico(), $orden, ['mechanic_id' => $this->mec('X')->id])
            ->assertForbidden();

        $this->assertSame(0, RepairOrderItem::where('repair_order_id', $orden->id)->count());
    }

    public function test_orden_cerrada_no_acepta_mano_de_obra(): void
    {
        $orden = $this->ordenEn('entregado');

        $this->agregarLabor($this->admin(), $orden, ['mechanic_id' => $this->mec('X')->id])
            ->assertForbidden();

        $this->assertSame(0, RepairOrderItem::where('repair_order_id', $orden->id)->count());
    }

    public function test_el_mecanico_no_recibe_snapshot_ni_modelo_en_la_orden(): void
    {
        $servicio = $this->servicio('Afinación 4 cilindros', 50);
        $usuario = $this->mecanico();
        $responsable = Mechanic::create([
            'name' => 'Responsable', 'user_id' => $usuario->id,
            'gender_id' => Gender::firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::firstOrCreate(['name' => 'General'])->id,
        ]);
        $orden = $this->ordenEn('en-reparacion', $responsable);
        $this->agregarLabor($this->admin(), $orden, [
            'mechanic_id' => $this->mec('Juan')->id, 'service_id' => $servicio->id,
        ]);

        $this->actingAs($usuario)->get(route('repair-orders.show', $orden))
            ->assertInertia(fn ($page) => $page
                ->missing('orden.items.0.commission_amount_snapshot')
                ->missing('orden.items.0.commission_model')
                ->missing('orden.items.0.unit_price')
                ->where('services', [])
            );
    }

    // ── Histórico legacy ──────────────────────────────────────────────────

    public function test_concepto_existente_queda_legacy_y_sin_servicio(): void
    {
        $orden = RepairOrder::factory()->create();
        $item = $this->mano($orden, ['subtotal' => 1000, 'unit_price' => 1000]);

        $item->refresh();
        $this->assertSame(RepairOrderItem::MODELO_LEGACY, $item->commission_model);
        $this->assertNull($item->service_id);
        $this->assertNull($item->mechanic_id);
        $this->assertNull($item->commission_amount_snapshot);
    }

    public function test_reporte_legacy_usa_commission_rate_del_responsable(): void
    {
        $mes = Carbon::now();
        $responsable = $this->mec('Historico', 10);
        $orden = $this->ordenEn('reparado', $responsable, $mes);
        $this->mano($orden, ['subtotal' => 1000, 'unit_price' => 1000]);

        $this->actingAs($this->admin())
            ->get(route('reports.mechanics', ['month' => $mes->month, 'year' => $mes->year]))
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.name', 'Historico')
                ->where('rows.0.commission_legacy', 100)
                ->where('rows.0.commission_fixed', 0)
                ->where('rows.0.commission', 100)
            );
    }

    public function test_texto_libre_nuevo_no_usa_commission_rate_como_fallback(): void
    {
        $mes = Carbon::now();
        $mec = $this->mec('Sin fallback', 10);
        $orden = $this->ordenEn('reparado', $mec, $mes);
        $this->crearConcepto([
            'repair_order_id' => $orden->id, 'type' => 'labor', 'description' => 'Libre',
            'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000,
            'mechanic_id' => $mec->id,
            'commission_model' => RepairOrderItem::MODELO_FIXED,
            'commission_amount_snapshot' => 0,
        ]);

        $this->actingAs($this->admin())
            ->get(route('reports.mechanics', ['month' => $mes->month, 'year' => $mes->year]))
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.commission_legacy', 0)
                ->where('rows.0.commission_fixed', 0)
                ->where('rows.0.commission', 0)
            );
    }

    public function test_reporte_fijo_atribuye_al_ejecutor_una_sola_vez(): void
    {
        $mes = Carbon::now();
        $ejecutor = $this->mec('Ejecutor');
        $responsable = $this->mec('Responsable');
        $orden = $this->ordenEn('reparado', $responsable, $mes);
        $servicio = $this->servicio('Afinación 4 cilindros', 50);

        $this->crearConcepto([
            'repair_order_id' => $orden->id, 'type' => 'labor', 'description' => 'Afinación',
            'quantity' => 3.5, 'unit_price' => 500, 'subtotal' => 1750,
            'service_id' => $servicio->id, 'mechanic_id' => $ejecutor->id,
            'commission_model' => RepairOrderItem::MODELO_FIXED,
            'commission_amount_snapshot' => 50,
        ]);

        $this->actingAs($this->admin())
            ->get(route('reports.mechanics', ['month' => $mes->month, 'year' => $mes->year]))
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.name', 'Ejecutor')
                ->where('rows.0.commission_fixed', 50)
                ->where('rows.0.commission', 50)
                ->where('rows.1.name', 'Responsable')
                ->where('rows.1.commission_fixed', 0)
                ->where('rows.1.commission', 0)
            );
    }

    public function test_snapshot_del_reporte_no_depende_del_catalogo_vivo(): void
    {
        $mes = Carbon::now();
        $ejecutor = $this->mec('Ejecutor');
        $orden = $this->ordenEn('reparado', null, $mes);
        $servicio = $this->servicio('Afinación 4 cilindros', 50);
        $this->crearConcepto([
            'repair_order_id' => $orden->id, 'type' => 'labor', 'description' => 'Afinación',
            'quantity' => 1, 'unit_price' => 500, 'subtotal' => 500,
            'service_id' => $servicio->id, 'mechanic_id' => $ejecutor->id,
            'commission_model' => RepairOrderItem::MODELO_FIXED,
            'commission_amount_snapshot' => 50,
        ]);

        $servicio->update(['commission_amount' => 999]);

        $this->actingAs($this->admin())
            ->get(route('reports.mechanics', ['month' => $mes->month, 'year' => $mes->year]))
            ->assertInertia(fn ($page) => $page->where('rows.0.commission_fixed', 50));
    }

    // ── Periodo, totales y mass assignment (revisión I1-I3) ────────────────

    public function test_comision_fija_pertenece_al_mes_del_registro_aunque_la_orden_cambie_despues(): void
    {
        $mesA = Carbon::create(2026, 3, 15, 12);
        $mesB = Carbon::create(2026, 4, 10, 12);
        $ejecutor = $this->mec('Ejecutor');
        $servicio = $this->servicio('Afinación 4 cilindros', 50);
        $orden = $this->ordenEn('reparado', null, $mesA);

        $this->crearConcepto([
            'repair_order_id' => $orden->id, 'type' => 'labor', 'description' => 'Afinación',
            'quantity' => 1, 'unit_price' => 500, 'subtotal' => 500,
            'service_id' => $servicio->id, 'mechanic_id' => $ejecutor->id,
            'commission_model' => RepairOrderItem::MODELO_FIXED, 'commission_amount_snapshot' => 50,
            'created_at' => $mesA, 'updated_at' => $mesA,
        ]);

        // Cambios posteriores en la orden (pago, estado, is_paid) en el mes B.
        $orden->forceFill(['updated_at' => $mesB, 'is_paid' => true])->saveQuietly();

        $this->assertSame(50.0, $this->fila($this->reporte($mesA), 'Ejecutor')['commission_fixed']);
        $this->assertSame(0.0, $this->fila($this->reporte($mesB), 'Ejecutor')['commission_fixed']);
    }

    public function test_total_de_ordenes_cuenta_ordenes_distintas_no_participaciones(): void
    {
        $mes = Carbon::now();
        $a = $this->mec('A');
        $b = $this->mec('B');
        $c = $this->mec('C');
        $servicio = $this->servicio('Afinación 4 cilindros', 50);
        $orden = $this->ordenEn('reparado', $a, $mes);

        foreach ([[$b, 50], [$b, 30], [$c, 40]] as [$ejecutor, $monto]) {
            $this->crearConcepto([
                'repair_order_id' => $orden->id, 'type' => 'labor', 'description' => 'Afinación',
                'quantity' => 1, 'unit_price' => 500, 'subtotal' => 500,
                'service_id' => $servicio->id, 'mechanic_id' => $ejecutor->id,
                'commission_model' => RepairOrderItem::MODELO_FIXED, 'commission_amount_snapshot' => $monto,
            ]);
        }

        $props = $this->reporte($mes);

        $this->assertSame(1, $props['totals']['orders']);
        $this->assertSame(80.0, $this->fila($props, 'B')['commission_fixed']);
        $this->assertSame(40.0, $this->fila($props, 'C')['commission_fixed']);
        $this->assertSame(0.0, $this->fila($props, 'A')['commission_fixed']);
        $this->assertSame(1, $this->fila($props, 'A')['orders']);
        $this->assertSame(1, $this->fila($props, 'B')['orders']);
        $this->assertSame(1, $this->fila($props, 'C')['orders']);
    }

    public function test_la_peticion_no_puede_imponer_modelo_ni_snapshot(): void
    {
        $servicio = $this->servicio('Afinación 4 cilindros', 50);
        $orden = RepairOrder::factory()->create();

        $this->agregarLabor($this->admin(), $orden, [
            'mechanic_id' => $this->mec('X')->id, 'service_id' => $servicio->id,
            'commission_model' => 'legacy', 'commission_amount_snapshot' => 9999,
        ])->assertSessionHasNoErrors();

        $item = RepairOrderItem::where('repair_order_id', $orden->id)->firstOrFail();
        $this->assertSame(RepairOrderItem::MODELO_FIXED, $item->commission_model);
        $this->assertSame('50.00', (string) $item->commission_amount_snapshot);
    }

    public function test_texto_libre_ignora_snapshot_enviado_por_la_peticion(): void
    {
        $orden = RepairOrder::factory()->create();

        $this->agregarLabor($this->admin(), $orden, [
            'mechanic_id' => $this->mec('Y')->id,
            'commission_model' => 'legacy', 'commission_amount_snapshot' => 9999,
        ])->assertSessionHasNoErrors();

        $item = RepairOrderItem::where('repair_order_id', $orden->id)->firstOrFail();
        $this->assertSame(RepairOrderItem::MODELO_FIXED, $item->commission_model);
        $this->assertSame('0.00', (string) $item->commission_amount_snapshot);
    }

    public function test_el_modelo_no_acepta_asignacion_masiva_de_campos_derivados(): void
    {
        $item = (new RepairOrderItem)->fill([
            'commission_model' => RepairOrderItem::MODELO_FIXED,
            'commission_amount_snapshot' => 9999,
        ]);

        $this->assertNotSame(RepairOrderItem::MODELO_FIXED, $item->commission_model);
        $this->assertNull($item->commission_amount_snapshot);
    }

    // ── Servicio inactivo histórico y no impacto A2 ───────────────────────

    public function test_servicio_inactivo_sigue_resolviendo_en_conceptos_historicos(): void
    {
        $servicio = $this->servicio('Afinación 6 cilindros', 100);
        $orden = RepairOrder::factory()->create();
        $this->agregarLabor($this->admin(), $orden, [
            'mechanic_id' => $this->mec('Juan')->id, 'service_id' => $servicio->id,
        ])->assertSessionHasNoErrors();

        $servicio->update(['is_active' => false]);

        $item = RepairOrderItem::where('repair_order_id', $orden->id)->firstOrFail();
        $this->assertSame('Afinación 6 cilindros', $item->service?->name);
        $this->assertSame('100.00', (string) $item->commission_amount_snapshot);

        $this->actingAs($this->admin())
            ->get(route('repair-orders.show', $orden))
            ->assertOk();
    }

    public function test_la_comision_fija_no_modifica_el_estado_financiero_a2(): void
    {
        $admin = $this->admin();
        $servicio = $this->servicio('Afinación 4 cilindros', 50);
        $ejecutor = $this->mec('Ejecutor');
        $con = RepairOrder::factory()->create(['tax_rate' => 16]);
        $sin = RepairOrder::factory()->create(['tax_rate' => 16]);

        $this->agregarLabor($admin, $con, [
            'mechanic_id' => $ejecutor->id, 'service_id' => $servicio->id, 'quantity' => 2, 'unit_price' => 500,
        ])->assertSessionHasNoErrors();
        $this->agregarLabor($admin, $sin, [
            'mechanic_id' => $ejecutor->id, 'quantity' => 2, 'unit_price' => 500,
        ])->assertSessionHasNoErrors();

        foreach ([$con, $sin] as $orden) {
            $this->actingAs($admin)->post(route('payments.store', $orden), [
                'amount' => 300, 'payment_method' => 'Cash',
            ])->assertSessionHasNoErrors();
        }

        $conFijo = $con->fresh();
        $sinFijo = $sin->fresh();

        // Misma fuente A2 para ambas órdenes: solo cambia la comisión, que no entra en el cálculo financiero.
        $this->assertSame(
            app(CalculateOrderTotalsAction::class)->calcular($sinFijo),
            app(CalculateOrderTotalsAction::class)->calcular($conFijo),
        );
        $this->assertSame(
            app(EstadoFinancieroAction::class)->execute($sinFijo),
            app(EstadoFinancieroAction::class)->execute($conFijo),
        );
        $this->assertSame(300.0, app(EstadoFinancieroAction::class)->execute($conFijo)['total_pagado']);
    }

    public function test_orden_rechazada_no_aporta_comision_fija_a_pagar(): void
    {
        $mes = Carbon::now();
        $servicio = $this->servicio('Afinación 4 cilindros', 50);
        $ejecutor = $this->mec('Ejecutor');

        $rechazada = $this->ordenEn('rechazado', null, $mes);
        $this->crearConcepto([
            'repair_order_id' => $rechazada->id, 'type' => 'labor', 'description' => 'Afinación',
            'quantity' => 1, 'unit_price' => 500, 'subtotal' => 500,
            'service_id' => $servicio->id, 'mechanic_id' => $ejecutor->id,
            'commission_model' => RepairOrderItem::MODELO_FIXED, 'commission_amount_snapshot' => 50,
        ]);

        $props = $this->reporte($mes);

        $this->assertSame(0.0, $this->fila($props, 'Ejecutor')['commission_fixed']);
        $this->assertSame(0.0, $this->fila($props, 'Ejecutor')['commission']);
        $this->assertSame(0, $this->fila($props, 'Ejecutor')['orders']);
        $this->assertSame(0.0, $props['totals']['commission_fixed']);
        $this->assertSame(0, $props['totals']['orders']);

        // Control: la misma comisión sí cuenta si la orden está reparada.
        $rechazada->forceFill(['status_id' => RepairOrderStatus::firstOrCreate(['slug' => 'reparado'], [
            'name' => 'Reparado', 'color_class' => 'bg-gray-500',
        ])->id])->saveQuietly();

        $this->assertSame(50.0, $this->fila($this->reporte($mes), 'Ejecutor')['commission_fixed']);
    }
}
