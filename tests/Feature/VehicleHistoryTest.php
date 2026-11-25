<?php

namespace Tests\Feature;

use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Actions\RepairOrders\EstadoFinancieroAction;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Vehículos V2 / Historial: expediente técnico por vehículo (admin/owner), visitas = recepciones.
 */
class VehicleHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER]);
    }

    private function mecanicoUser(): User
    {
        return User::factory()->create(['role' => User::ROLE_MECHANIC]);
    }

    private function mec(string $nombre): Mechanic
    {
        return Mechanic::create([
            'name' => $nombre,
            'gender_id' => Gender::firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::firstOrCreate(['name' => 'General'])->id,
        ]);
    }

    private function vehiculo(array $datos = []): Vehicle
    {
        $cliente = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);

        return Vehicle::factory()->create(array_merge(['client_id' => $cliente->id], $datos));
    }

    private function visita(Vehicle $vehiculo, array $datos = []): Recepcion
    {
        return Recepcion::factory()->create(array_merge([
            'client_id' => $vehiculo->client_id,
            'vehicle_id' => $vehiculo->id,
            'miles' => '50000',
            'symptoms' => 'Ruido en frenos',
        ], $datos));
    }

    private function orden(Recepcion $recepcion, string $slug, ?Mechanic $responsable = null): RepairOrder
    {
        $status = RepairOrderStatus::firstOrCreate(['slug' => $slug], [
            'name' => ucfirst($slug), 'color_class' => 'bg-gray-500',
        ]);

        return RepairOrder::factory()->create([
            'recepcion_id' => $recepcion->id,
            'client_id' => $recepcion->client_id,
            'vehicle_id' => $recepcion->vehicle_id,
            'mechanic_id' => $responsable?->id,
            'status_id' => $status->id,
        ]);
    }

    private function mano(RepairOrder $orden, ?Mechanic $ejecutor, float $subtotal = 500): void
    {
        RepairOrderItem::create([
            'repair_order_id' => $orden->id, 'type' => 'labor', 'description' => 'Trabajo',
            'quantity' => 1, 'unit_price' => $subtotal, 'subtotal' => $subtotal,
            'mechanic_id' => $ejecutor?->id,
        ]);
    }

    private function pagina(Vehicle $vehiculo, ?User $usuario = null): array
    {
        return $this->actingAs($usuario ?? $this->admin())
            ->get(route('vehicles.history', $vehiculo))
            ->viewData('page')['props'];
    }

    // ── Acceso ────────────────────────────────────────────────────────────

    public function test_admin_accede_al_historial(): void
    {
        $this->actingAs($this->admin())
            ->get(route('vehicles.history', $this->vehiculo()))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Vehicles/History'));
    }

    public function test_owner_accede_al_historial(): void
    {
        $this->actingAs($this->owner())
            ->get(route('vehicles.history', $this->vehiculo()))
            ->assertOk();
    }

    public function test_mecanico_recibe_403(): void
    {
        $this->actingAs($this->mecanicoUser())
            ->get(route('vehicles.history', $this->vehiculo()))
            ->assertForbidden();
    }

    public function test_invitado_redirige_a_login(): void
    {
        $this->get(route('vehicles.history', $this->vehiculo()))
            ->assertRedirectContains('login');
    }

    // ── Visitas y resumen ─────────────────────────────────────────────────

    public function test_vehiculo_sin_recepciones_muestra_estado_vacio(): void
    {
        $props = $this->pagina($this->vehiculo());

        $this->assertSame(0, $props['resumen']['visitas_totales']);
        $this->assertNull($props['resumen']['ultima_visita']);
        $this->assertCount(0, $props['visitas']);
    }

    public function test_recepcion_sin_orden_aparece_como_visita(): void
    {
        $vehiculo = $this->vehiculo();
        $recepcion = $this->visita($vehiculo, ['symptoms' => 'Fuga de aceite']);

        $visita = collect($this->pagina($vehiculo)['visitas'])->first();

        $this->assertSame($recepcion->id, $visita['id']);
        $this->assertSame('Fuga de aceite', $visita['problema']);
        $this->assertNull($visita['orden']);
    }

    public function test_numero_de_visitas_cuenta_recepciones_no_ordenes(): void
    {
        $vehiculo = $this->vehiculo();
        $this->visita($vehiculo);                                           // sin orden
        $this->orden($this->visita($vehiculo), 'reparado');                 // con orden
        $this->orden($this->visita($vehiculo), 'rechazado');                // rechazada, aun así es visita

        $props = $this->pagina($vehiculo);

        $this->assertSame(3, $props['resumen']['visitas_totales']);
        $this->assertCount(3, $props['visitas']);
    }

    public function test_ultima_visita_es_la_recepcion_mas_reciente(): void
    {
        $vehiculo = $this->vehiculo();
        $antigua = $this->visita($vehiculo);
        $antigua->forceFill(['created_at' => Carbon::create(2026, 1, 5, 10)])->saveQuietly();
        $reciente = $this->visita($vehiculo);
        $reciente->forceFill(['created_at' => Carbon::create(2026, 6, 1, 10)])->saveQuietly();

        $props = $this->pagina($vehiculo);

        $this->assertSame($reciente->id, $props['resumen']['ultima_recepcion_id']);
        $this->assertSame($reciente->id, collect($props['visitas'])->first()['id']);
    }

    public function test_ultimo_kilometraje_ignora_recepciones_sin_dato(): void
    {
        $vehiculo = $this->vehiculo();
        $anterior = $this->visita($vehiculo, ['miles' => '15000']);
        $anterior->forceFill(['created_at' => Carbon::create(2026, 1, 5, 10)])->saveQuietly();
        $reciente = $this->visita($vehiculo, ['miles' => null]);
        $reciente->forceFill(['created_at' => Carbon::create(2026, 6, 1, 10)])->saveQuietly();

        $this->assertSame(15000, $this->pagina($vehiculo)['resumen']['ultimo_kilometraje']);
    }

    // ── Marca y modelo ────────────────────────────────────────────────────

    public function test_marca_de_catalogo_con_fallback_al_texto_legacy(): void
    {
        $catalogo = $this->vehiculo(['brand' => 'Toyota', 'brand_id' => Brand::factory()->create(['name' => 'Nissan'])->id]);
        $legacy = $this->vehiculo(['brand' => 'Toyota', 'brand_id' => null]);

        $this->assertSame('Nissan', $this->pagina($catalogo)['vehicle']['marca']);
        $this->assertSame('Toyota', $this->pagina($legacy)['vehicle']['marca']);
    }

    // ── Responsable, ejecutores y conceptos ───────────────────────────────

    public function test_responsable_y_ejecutores_correctos(): void
    {
        $vehiculo = $this->vehiculo();
        $responsable = $this->mec('Responsable');
        $ejecutor = $this->mec('Ejecutor');
        $orden = $this->orden($this->visita($vehiculo), 'en-reparacion', $responsable);
        $this->mano($orden, $ejecutor);

        $visita = collect($this->pagina($vehiculo)['visitas'])->first();

        $this->assertSame('Responsable', $visita['orden']['mecanico']);
        $this->assertSame(['Ejecutor'], $visita['orden']['ejecutores']);
        $this->assertSame('Ejecutor', $visita['orden']['trabajos'][0]['ejecutor']);
    }

    public function test_conceptos_legacy_sin_ejecutor_no_fallan_ni_inventan_ejecutor(): void
    {
        $vehiculo = $this->vehiculo();
        $orden = $this->orden($this->visita($vehiculo), 'reparado', $this->mec('Responsable'));
        $this->mano($orden, null);

        $this->actingAs($this->admin())->get(route('vehicles.history', $vehiculo))->assertOk();

        $visita = collect($this->pagina($vehiculo)['visitas'])->first();
        $this->assertSame([], $visita['orden']['ejecutores']);
        $this->assertNull($visita['orden']['trabajos'][0]['ejecutor']);
    }

    // ── Seguridad de campos ───────────────────────────────────────────────

    public function test_no_expone_campos_de_comision(): void
    {
        $vehiculo = $this->vehiculo();
        $ejecutor = $this->mec('Ejecutor');
        $orden = $this->orden($this->visita($vehiculo), 'reparado', $ejecutor);
        $this->mano($orden, $ejecutor);

        $this->actingAs($this->admin())
            ->get(route('vehicles.history', $vehiculo))
            ->assertDontSee('commission_rate')
            ->assertDontSee('commission_model')
            ->assertDontSee('commission_amount_snapshot');
    }

    // ── Nueva recepción ───────────────────────────────────────────────────

    public function test_nueva_recepcion_precarga_cliente_y_vehiculo(): void
    {
        $vehiculo = $this->vehiculo();

        $url = $this->pagina($vehiculo)['vehicle']['nueva_recepcion_url'];

        $this->assertStringContainsString('client_id=' . $vehiculo->client_id, $url);
        $this->assertStringContainsString('vehicle_id=' . $vehiculo->id, $url);
        $this->assertStringContainsString('/recepciones/create', $url);
    }

    // ── Finanzas: reutiliza las acciones existentes ───────────────────────

    public function test_totales_usan_las_acciones_financieras_existentes(): void
    {
        $vehiculo = $this->vehiculo();
        $orden = $this->orden($this->visita($vehiculo), 'reparado', $this->mec('Responsable'));
        $this->mano($orden, null, 1000);

        $visita = collect($this->pagina($vehiculo)['visitas'])->first();
        $fresca = $orden->fresh();

        $this->assertEquals(
            app(CalculateOrderTotalsAction::class)->calcular($fresca)['total'],
            $visita['orden']['finanzas']['total'],
        );
        $this->assertEquals(
            app(EstadoFinancieroAction::class)->execute($fresca)['saldo_autorizado'],
            $visita['orden']['finanzas']['saldo'],
        );
    }

    // ── Órdenes rechazadas ────────────────────────────────────────────────

    public function test_orden_rechazada_conserva_la_visita_sin_trabajos_ni_montos(): void
    {
        $vehiculo = $this->vehiculo();
        $recepcion = $this->visita($vehiculo);
        $orden = $this->orden($recepcion, 'rechazado', $this->mec('Responsable'));
        $this->mano($orden, $this->mec('Ejecutor'), 800);

        $props = $this->pagina($vehiculo);
        $visita = collect($props['visitas'])->first();

        $this->assertSame($recepcion->id, $visita['id']);
        $this->assertTrue($visita['orden']['rechazada']);
        $this->assertFalse($visita['orden']['terminada']);
        $this->assertSame([], $visita['orden']['trabajos']);
        $this->assertSame([], $visita['orden']['ejecutores']);
        $this->assertNull($visita['orden']['finanzas']);
        $this->assertSame(1, $props['resumen']['visitas_totales']);
    }
}
