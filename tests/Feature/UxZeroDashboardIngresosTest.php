<?php

namespace Tests\Feature;

use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\Payment;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * UX-0: "Ingresos del mes" del Dashboard = SUM(payments.amount) del mes en curso.
 * Nunca usa estimated_cost ni órdenes entregadas. Mechanic no recibe la métrica.
 */
class UxZeroDashboardIngresosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function mecanicoConUsuario(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        Mechanic::create([
            'name'             => 'Mecánico UX-0',
            'user_id'          => $user->id,
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        return $user;
    }

    /** Orden en estado entregado, actualizada este mes, con un costo técnico dado. */
    private function ordenEntregada(float $estimadoCost): RepairOrder
    {
        $status    = RepairOrderStatus::query()->firstOrCreate(
            ['slug' => 'entregado'],
            ['name' => 'Entregado', 'color_class' => 'bg-gray-500']
        );
        $recepcion = Recepcion::factory()->create();

        return RepairOrder::factory()->create([
            'recepcion_id'   => $recepcion->id,
            'client_id'      => $recepcion->client_id,
            'vehicle_id'     => $recepcion->vehicle_id,
            'status_id'      => $status->id,
            'estimated_cost' => $estimadoCost,
        ]);
    }

    private function pagar(RepairOrder $orden, float $monto): Payment
    {
        return Payment::create([
            'repair_order_id' => $orden->id,
            'amount'          => $monto,
            'payment_method'  => 'Cash',
        ]);
    }

    public function test_ingresos_del_mes_suma_los_pagos_registrados_del_mes(): void
    {
        $orden = $this->ordenEntregada(0);
        $this->pagar($orden, 1200.50);
        $this->pagar($orden, 300.00);

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $this->assertEquals(1500.50, $response->inertiaProps('stats.ingresosMes'));
    }

    public function test_orden_entregada_sin_pago_no_aumenta_ingresos_del_mes(): void
    {
        // Costo técnico alto y entregada este mes, pero sin cobro: no es ingreso.
        $this->ordenEntregada(5000);

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $this->assertEquals(0, $response->inertiaProps('stats.ingresosMes'));
    }

    public function test_pago_registrado_aumenta_ingresos_del_mes_aunque_costo_tecnico_sea_mayor(): void
    {
        // El cobro (800) cuenta; el costo técnico (5000) no se suma ni se usa como máximo.
        $orden = $this->ordenEntregada(5000);
        $this->pagar($orden, 800);

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $this->assertEquals(800, $response->inertiaProps('stats.ingresosMes'));
    }

    public function test_pagos_de_meses_anteriores_no_cuentan_en_ingresos_del_mes(): void
    {
        $orden = $this->ordenEntregada(0);
        $pagoAnterior = $this->pagar($orden, 999);
        $pagoAnterior->forceFill(['created_at' => now()->subMonthNoOverflow()])->save();

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $this->assertEquals(0, $response->inertiaProps('stats.ingresosMes'));
    }

    public function test_mechanic_no_recibe_ingresos_del_mes_aunque_existan_pagos(): void
    {
        $orden = $this->ordenEntregada(0);
        $this->pagar($orden, 500);

        $this->actingAs($this->mecanicoConUsuario())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.ingresosMes', null)
            );
    }
}
