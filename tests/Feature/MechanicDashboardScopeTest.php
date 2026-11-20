<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\Part;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Alcance del dashboard del mecánico: solo recepciones y datos de sus órdenes asignadas.
 */
class MechanicDashboardScopeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Mechanic} */
    private function mechanic(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $mechanic = Mechanic::create([
            'name'             => 'Mecánico de prueba',
            'user_id'          => $user->id,
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        return [$user, $mechanic];
    }

    /** Recepción de un cliente con teléfono conocido, vinculada a una orden (opcional). */
    private function recepcionDe(string $telefono, string $nombre, ?Mechanic $mechanic): Recepcion
    {
        $client = Client::factory()->create([
            'first_name' => $nombre,
            'last_name'  => 'Prueba',
            'phone'      => $telefono,
            'rfc'        => 'XAXX010101000',
        ]);
        $recepcion = Recepcion::factory()->create(['client_id' => $client->id]);

        if ($mechanic) {
            RepairOrder::factory()->create([
                'recepcion_id' => $recepcion->id,
                'client_id'    => $client->id,
                'vehicle_id'   => $recepcion->vehicle_id,
                'mechanic_id'  => $mechanic->id,
            ]);
        }

        return $recepcion;
    }

    public function test_mechanic_receives_only_recepciones_of_assigned_orders(): void
    {
        [$user, $mechanic] = $this->mechanic();
        [, $otroMecanico] = $this->mechanic();

        $propia  = $this->recepcionDe('9510000001', 'Propia', $mechanic);
        $ajena   = $this->recepcionDe('9510000002', 'Ajena', $otroMecanico);
        $sinOrden = $this->recepcionDe('9510000003', 'SinOrden', null);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('recentRecepcions.data', 1)
                ->where('recentRecepcions.data.0.id', $propia->id)
            );
    }

    public function test_mechanic_does_not_receive_phones_of_other_clients(): void
    {
        [$user, $mechanic] = $this->mechanic();
        [, $otroMecanico] = $this->mechanic();

        $this->recepcionDe('9511112222', 'Ajena', $otroMecanico);
        $this->recepcionDe('9513334444', 'SinOrden', null);
        $this->recepcionDe('9515556666', 'Propia', $mechanic);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('9511112222')
            ->assertDontSee('9513334444')
            // Los datos de contacto y fiscales tampoco se envían del cliente propio
            ->assertDontSee('9515556666')
            ->assertDontSee('XAXX010101000')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('recentRecepcions.data.0.client.phone')
                ->missing('recentRecepcions.data.0.client.rfc')
            );
    }

    public function test_mechanic_search_cannot_reach_other_clients(): void
    {
        [$user, $mechanic] = $this->mechanic();
        [, $otroMecanico] = $this->mechanic();

        $this->recepcionDe('9517778888', 'Ajena', $otroMecanico);
        $this->recepcionDe('9519990000', 'Propia', $mechanic);

        // Buscar por nombre y por teléfono de un cliente ajeno: no debe aparecer nada
        $this->actingAs($user)
            ->get(route('dashboard', ['search' => 'Ajena']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('recentRecepcions.data', 0));

        $this->actingAs($user)
            ->get(route('dashboard', ['search' => '9517778888']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('recentRecepcions.data', 0));
    }

    public function test_mechanic_stats_are_scoped_and_hide_global_figures(): void
    {
        [$user, $mechanic] = $this->mechanic();
        [, $otroMecanico] = $this->mechanic();

        $this->recepcionDe('9510000011', 'Ajena', $otroMecanico);
        $this->recepcionDe('9510000012', 'SinOrden', null);
        $this->recepcionDe('9510000013', 'Propia', $mechanic);
        Part::create([
            'name' => 'Filtro', 'sku' => 'FIL-' . uniqid(), 'sale_price' => 50, 'cost_price' => 30,
            'stock' => 0, 'low_stock_threshold' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.total', 1)
                ->where('stats.today', 1)
                ->where('stats.porCotizar', null)
                ->where('stats.stockAlertas', null)
                ->where('stats.ingresosMes', null)
            );
    }

    public function test_mechanic_without_mechanic_record_sees_no_orders_or_recepciones(): void
    {
        // Usuario con rol mecánico pero sin registro en mechanics: alcance vacío, nunca global
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);
        [, $otroMecanico] = $this->mechanic();

        $this->recepcionDe('9510000021', 'Ajena', $otroMecanico);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.ordenesActivas', 0)
                ->where('stats.total', 0)
                ->has('recentRecepcions.data', 0)
            );
    }

    public function test_admin_dashboard_keeps_global_information(): void
    {
        [, $mechanic] = $this->mechanic();
        [, $otroMecanico] = $this->mechanic();

        $this->recepcionDe('9510000031', 'Ajena', $otroMecanico);
        $this->recepcionDe('9510000032', 'SinOrden', null);
        $this->recepcionDe('9510000033', 'Propia', $mechanic);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.total', 3)
                ->has('stats.porCotizar')
                ->has('stats.stockAlertas')
                ->has('recentRecepcions.data', 3)
            );
    }
}
