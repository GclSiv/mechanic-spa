<?php

namespace Tests\Feature;

use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * IMPLEMENTACIÓN-UI-1: métricas nuevas del Dashboard.
 * - Esperando piezas: órdenes activas en slug espera-piezas.
 * - Listos para recoger: órdenes activas en slug reparado (no reutiliza pendingPickupCount).
 * - Esperando autorización: órdenes activas con pendiente_autorizacion > 0 (derivada, solo admin).
 */
class DashboardUi1StatsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** @return array{0: User, 1: Mechanic} */
    private function mecanico(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $mechanic = Mechanic::create([
            'name'             => 'Mecánico UI-1',
            'user_id'          => $user->id,
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        return [$user, $mechanic];
    }

    private function estado(string $slug): RepairOrderStatus
    {
        return RepairOrderStatus::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'color_class' => 'bg-gray-500']
        );
    }

    private function orden(string $slug, ?Mechanic $mechanic = null): RepairOrder
    {
        return RepairOrder::factory()->create([
            'tax_rate'     => 16,
            'status_id'    => $this->estado($slug)->id,
            'mechanic_id'  => $mechanic?->id,
        ]);
    }

    /** Concepto de mano de obra sin autorizar: genera pendiente_autorizacion > 0. */
    private function conceptoPendiente(RepairOrder $orden, float $subtotal = 500): void
    {
        RepairOrderItem::create([
            'repair_order_id' => $orden->id, 'type' => 'labor', 'description' => 'Mano de obra',
            'quantity' => 1, 'unit_price' => $subtotal, 'subtotal' => $subtotal,
        ]);
    }

    public function test_admin_cuenta_esperando_piezas_listos_y_autorizacion(): void
    {
        $admin = $this->admin();

        $this->orden('espera-piezas');
        $this->orden('espera-piezas');
        $this->orden('reparado');

        // Pendiente de autorización: orden activa con concepto sin autorizar.
        $pendiente = $this->orden('en-reparacion');
        $this->conceptoPendiente($pendiente);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.esperandoPiezas', 2)
                ->where('stats.listosRecoger', 1)
                ->where('stats.esperandoAutorizacion', 1)
            );
    }

    public function test_esperando_autorizacion_cuenta_orden_una_sola_vez_y_excluye_entregadas(): void
    {
        $admin = $this->admin();

        // Dos conceptos pendientes en la misma orden: cuenta como 1 orden.
        $dosConceptos = $this->orden('diagnostico');
        $this->conceptoPendiente($dosConceptos, 300);
        $this->conceptoPendiente($dosConceptos, 200);

        // Entregada con concepto pendiente: no es activa, no cuenta.
        $entregada = $this->orden('entregado');
        $this->conceptoPendiente($entregada);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.esperandoAutorizacion', 1)
            );
    }

    public function test_mecanico_no_recibe_esperando_autorizacion_y_solo_cuenta_sus_ordenes(): void
    {
        [$user, $mechanic] = $this->mecanico();
        [, $otro]          = $this->mecanico();

        $this->orden('espera-piezas', $mechanic);
        $this->orden('espera-piezas', $otro);
        $this->orden('reparado', $mechanic);
        $this->orden('reparado', $otro);
        $this->orden('reparado', $otro);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.esperandoAutorizacion', null)
                ->where('stats.esperandoPiezas', 1)
                ->where('stats.listosRecoger', 1)
            );
    }

    public function test_mecanico_sin_registro_no_ve_conteos_ajenos(): void
    {
        $sinRegistro = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $this->orden('espera-piezas');
        $this->orden('reparado');

        $this->actingAs($sinRegistro)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.esperandoPiezas', 0)
                ->where('stats.listosRecoger', 0)
                ->where('stats.esperandoAutorizacion', null)
            );
    }
}
