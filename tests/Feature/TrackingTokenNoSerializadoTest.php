<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El tracking_token es un secreto de acceso público: nunca debe llegar crudo a Inertia/JSON.
 * El backend sigue pudiendo usarlo ($order->tracking_token) y la URL pública sigue funcionando.
 */
class TrackingTokenNoSerializadoTest extends TestCase
{
    use RefreshDatabase;

    private function orden(?Mechanic $mechanic = null): RepairOrder
    {
        $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $vehiculo = Vehicle::factory()->create(['client_id' => $client->id]);
        $recepcion = Recepcion::factory()->create(['client_id' => $client->id, 'vehicle_id' => $vehiculo->id]);

        return RepairOrder::factory()->create([
            'recepcion_id' => $recepcion->id,
            'client_id' => $client->id,
            'vehicle_id' => $vehiculo->id,
            'mechanic_id' => $mechanic?->id,
        ]);
    }

    /** @return array{0: User, 1: Mechanic} */
    private function mecanico(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);
        $mechanic = Mechanic::create([
            'name' => 'Mecánico de prueba',
            'user_id' => $user->id,
            'gender_id' => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        return [$user, $mechanic];
    }

    public function test_el_modelo_no_serializa_el_token_pero_el_backend_lo_conserva(): void
    {
        $order = $this->orden();

        $this->assertArrayNotHasKey('tracking_token', $order->toArray());
        $this->assertArrayNotHasKey('tracking_token', $order->fresh()->toArray());
        $this->assertSame(48, strlen($order->tracking_token));
        $this->assertSame($order->tracking_token, RepairOrder::find($order->id)->getAttributes()['tracking_token']);
    }

    public function test_admin_no_recibe_el_token_crudo_en_listados_y_expediente(): void
    {
        $order = $this->orden();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        foreach ([
            route('dashboard'),
            route('repair-orders.lista'),
            route('clients.show', $order->client_id),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertDontSee($order->tracking_token, false);
        }
    }

    public function test_admin_detalle_solo_expone_el_token_dentro_de_tracking_url(): void
    {
        $order = $this->orden();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get(route('repair-orders.show', $order))
            ->assertInertia(fn ($page) => $page
                ->missing('orden.tracking_token')
                ->where('comunicacion.tracking_url', route('tracking.show', ['token' => $order->tracking_token])));
    }

    public function test_mecanico_no_recibe_el_token_ni_tracking_url(): void
    {
        [$user, $mechanic] = $this->mecanico();
        $order = $this->orden($mechanic);

        foreach ([
            route('repair-orders.lista'),
            route('repair-orders.show', $order),
        ] as $url) {
            $this->actingAs($user)->get($url)
                ->assertDontSee($order->tracking_token, false)
                ->assertDontSee('/seguimiento/' . $order->tracking_token, false);
        }
    }

    public function test_la_url_publica_de_seguimiento_sigue_resolviendo(): void
    {
        $order = $this->orden();

        $this->get(route('tracking.show', ['token' => $order->tracking_token]))->assertOk();
    }
}
