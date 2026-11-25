<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\User;
use App\Support\ConteoOrdenes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Tablero Taller / Mis órdenes (IMPLEMENTACIÓN-UI-3): alcance por rol, columnas activas,
 * conteos compartidos y transiciones validadas por el backend.
 */
class TallerUi3KanbanTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Mechanic} */
    private function mechanic(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $mechanic = Mechanic::create([
            'name'             => 'Mecánico tablero',
            'user_id'          => $user->id,
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        return [$user, $mechanic];
    }

    private function estado(string $slug): RepairOrderStatus
    {
        // Las migraciones solo insertan algunos estados; el resto se crea con la misma forma que CatalogSeeder.
        return RepairOrderStatus::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'color_class' => 'bg-gray-500'],
        );
    }

    private function orden(string $slug, ?Mechanic $mechanic): RepairOrder
    {
        $client = Client::factory()->create(['rfc' => 'XAXX010101000']);
        $recepcion = Recepcion::factory()->create(['client_id' => $client->id]);

        return RepairOrder::factory()->create([
            'recepcion_id' => $recepcion->id,
            'client_id'    => $client->id,
            'vehicle_id'   => $recepcion->vehicle_id,
            'mechanic_id'  => $mechanic?->id,
            'status_id'    => $this->estado($slug)->id,
        ]);
    }

    public function test_admin_board_excludes_closed_orders_and_uses_shared_counts(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [, $mec] = $this->mechanic();

        $activa   = $this->orden('en-reparacion', $mec);
        $entregada = $this->orden('entregado', $mec);
        $rechazada = $this->orden('rechazado', null);

        $this->actingAs($admin)
            ->get(route('repair-orders.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has("ordersByStatus.{$activa->status_id}", 1)
                ->missing("ordersByStatus.{$entregada->status_id}")
                ->missing("ordersByStatus.{$rechazada->status_id}")
                ->where('conteos', ConteoOrdenes::flujo(null))
                ->where('isAdmin', true)
            );
    }

    public function test_mechanic_board_contains_only_assigned_orders(): void
    {
        [$user, $mec] = $this->mechanic();
        [, $otro] = $this->mechanic();

        $propia = $this->orden('diagnostico', $mec);
        $ajena  = $this->orden('diagnostico', $otro);

        $this->actingAs($user)
            ->get(route('repair-orders.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has("ordersByStatus.{$propia->status_id}", 1)
                ->where("ordersByStatus.{$propia->status_id}.0.id", $propia->id)
                ->where('conteos', ConteoOrdenes::flujo($mec->id))
                ->where('isAdmin', false)
            );

        $this->assertNotSame($ajena->id, $propia->id);
    }

    public function test_mechanic_payload_has_no_financial_fields(): void
    {
        [$user, $mec] = $this->mechanic();
        $this->orden('reparado', $mec);

        $this->actingAs($user)
            ->get(route('repair-orders.index'))
            ->assertOk()
            ->assertDontSee('saldo_autorizado')
            ->assertDontSee('pendiente_autorizacion')
            ->assertDontSee('estimated_cost');
    }

    public function test_mechanic_editable_statuses_are_only_operational(): void
    {
        [$user] = $this->mechanic();

        $this->actingAs($user)
            ->get(route('repair-orders.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('estadosEditables', RepairOrderStatus::OPERATIVOS_MECANICO)
            );
    }

    public function test_admin_editable_statuses_are_operativos_only(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('repair-orders.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('estadosEditables', ['recibido', 'diagnostico', 'espera-piezas', 'en-reparacion', 'reparado'])
            );
    }

    public function test_admin_editable_statuses_exclude_closing_states(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('repair-orders.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('estadosEditables', fn ($estados) => collect($estados)
                    ->intersect(RepairOrderStatus::CERRADOS)
                    ->isEmpty())
            );
    }

    public function test_mechanic_cannot_close_or_reject_from_the_board(): void
    {
        [$user, $mec] = $this->mechanic();
        $orden = $this->orden('reparado', $mec);

        foreach (['entregado', 'rechazado'] as $slug) {
            $this->actingAs($user)
                ->patch(route('repair-orders.status.update', $orden), ['status_id' => $this->estado($slug)->id])
                ->assertForbidden();
        }

        $this->assertSame($this->estado('reparado')->id, $orden->fresh()->status_id);
    }

    public function test_mechanic_cannot_change_a_closed_order_from_the_board(): void
    {
        [$user, $mec] = $this->mechanic();
        $cerrada = $this->orden('entregado', $mec);

        $this->actingAs($user)
            ->patch(route('repair-orders.status.update', $cerrada), ['status_id' => $this->estado('diagnostico')->id])
            ->assertForbidden();

        $this->assertSame($this->estado('entregado')->id, $cerrada->fresh()->status_id);
    }

    public function test_authorization_pending_is_not_a_board_status(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $props = $this->actingAs($admin)
            ->get(route('repair-orders.index'))
            ->viewData('page')['props'];

        $slugs = collect($props['statuses'])->pluck('slug');

        $this->assertFalse($slugs->contains(fn ($slug) => str_contains((string) $slug, 'autoriza')));
    }
}
