<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPhoto;
use App\Models\Recepcion;
use App\Models\User;
use App\Models\Vehicle;
use App\Policies\ClientPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * B1.1: expediente del cliente (ruta show, autorización, relaciones y payload básico).
 */
class ClientExpedienteTest extends TestCase
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

    private function mecanico(): User
    {
        return User::factory()->create(['role' => User::ROLE_MECHANIC]);
    }

    private function cliente(string $nombre, string $telefono): Client
    {
        return Client::factory()->create([
            'first_name' => $nombre,
            'last_name'  => 'Prueba',
            'phone'      => $telefono,
        ]);
    }

    private function vehiculo(Client $cliente, string $placa): Vehicle
    {
        return Vehicle::factory()->create(['client_id' => $cliente->id, 'plate' => $placa]);
    }

    public function test_admin_can_open_client_expediente(): void
    {
        $cliente = $this->cliente('Admin', '9511111111');

        $this->actingAs($this->admin())
            ->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Clients/Show')
                ->where('client.id', $cliente->id)
            );
    }

    public function test_owner_can_open_client_expediente(): void
    {
        $cliente = $this->cliente('Owner', '9512222222');

        $this->actingAs($this->owner())
            ->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Clients/Show'));
    }

    public function test_mechanic_cannot_open_client_expediente(): void
    {
        $cliente = $this->cliente('Restringido', '9513333333');

        $this->actingAs($this->mecanico())
            ->get(route('clients.show', $cliente))
            ->assertForbidden();
    }

    public function test_client_policy_denies_mechanic_and_allows_admin(): void
    {
        $cliente = $this->cliente('Politica', '9514444444');

        $this->assertTrue((new ClientPolicy())->view($this->admin(), $cliente));
        $this->assertTrue((new ClientPolicy())->view($this->owner(), $cliente));
        $this->assertFalse((new ClientPolicy())->view($this->mecanico(), $cliente));
    }

    public function test_payload_contains_the_requested_client_and_its_summary(): void
    {
        $cliente = $this->cliente('Juan', '9515555555');
        $this->vehiculo($cliente, 'AAA-111');
        $this->vehiculo($cliente, 'BBB-222');

        $this->actingAs($this->admin())
            ->get(route('clients.show', $cliente))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('client.id', $cliente->id)
                ->where('client.first_name', 'Juan')
                ->where('client.phone', '9515555555')
                ->where('resumen.vehiculos', 2)
                ->where('resumen.ordenes', 0)
                ->where('resumen.ultima_visita', null)
                ->has('vehiculos', 2)
            );
    }

    public function test_vehicles_belong_only_to_that_client(): void
    {
        $cliente = $this->cliente('Propio', '9516666666');
        $otro    = $this->cliente('Ajeno', '9517777777');

        $mio = $this->vehiculo($cliente, 'MIO-001');
        $this->vehiculo($otro, 'AJE-999');

        $this->actingAs($this->admin())
            ->get(route('clients.show', $cliente))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('vehiculos', 1)
                ->where('vehiculos.0.id', $mio->id)
                ->where('vehiculos.0.plate', 'MIO-001')
            );
    }

    public function test_expediente_does_not_expose_another_clients_data(): void
    {
        $cliente = $this->cliente('Visible', '9518888888');
        $otro    = $this->cliente('Oculto', '9519999999');
        $this->vehiculo($otro, 'OCU-777');

        $this->actingAs($this->admin())
            ->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertDontSee('9519999999')
            ->assertDontSee('OCU-777')
            ->assertDontSee('Oculto');
    }

    public function test_kilometraje_and_visitas_come_from_the_latest_recepcion_of_each_vehicle(): void
    {
        $cliente = $this->cliente('Visitas', '9510101010');
        $vehiculo = $this->vehiculo($cliente, 'KMS-100');

        Recepcion::factory()->create([
            'client_id' => $cliente->id, 'vehicle_id' => $vehiculo->id, 'miles' => '50000',
        ]);
        Recepcion::factory()->create([
            'client_id' => $cliente->id, 'vehicle_id' => $vehiculo->id, 'miles' => '52500',
        ]);

        $this->actingAs($this->admin())
            ->get(route('clients.show', $cliente))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehiculos.0.visitas', 2)
                ->where('vehiculos.0.kilometraje', '52500')
                ->where('resumen.ultima_visita', now()->format('d/m/Y'))
            );
    }

    public function test_ultima_visita_uses_created_at_not_the_autoincrement_id(): void
    {
        $cliente  = $this->cliente('Cronologia', '9511515151');
        $vehiculo = $this->vehiculo($cliente, 'CRO-300');

        // Visita reciente, creada primero (ID menor)
        Recepcion::factory()->create([
            'client_id' => $cliente->id, 'vehicle_id' => $vehiculo->id, 'miles' => '52500',
        ]);

        // Recepción histórica cargada después (ID mayor) pero ocurrida hace 30 días
        $historica = Recepcion::factory()->create([
            'client_id' => $cliente->id, 'vehicle_id' => $vehiculo->id, 'miles' => '40000',
        ]);
        $historica->forceFill(['created_at' => now()->subDays(30)])->save();

        $this->assertGreaterThan(
            Recepcion::where('miles', '52500')->value('id'),
            $historica->id
        );

        $this->actingAs($this->admin())
            ->get(route('clients.show', $cliente))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehiculos.0.kilometraje', '52500')
                ->where('vehiculos.0.ultima_visita', now()->format('d/m/Y'))
                ->where('resumen.ultima_visita', now()->format('d/m/Y'))
            );
    }

    public function test_client_photos_belong_only_to_that_client(): void
    {
        $a = $this->cliente('FotoA', '9512525252');
        $b = $this->cliente('FotoB', '9513535353');
        $a->photos()->create(['path' => 'a/1.jpg']);
        $b->photos()->create(['path' => 'b/1.jpg']);

        $this->assertSame(1, $a->photos()->count());
        $this->assertTrue($a->photos->every(fn ($p) => $p->client_id === $a->id));
        $this->assertFalse($a->photos->contains('path', 'b/1.jpg'));
    }

    public function test_client_photos_relation_returns_the_client_photos(): void
    {
        $cliente = $this->cliente('Fotos', '9511212121');
        $cliente->photos()->create(['path' => 'recepciones/1/a.jpg']);

        $this->assertCount(1, $cliente->photos);
        $this->assertInstanceOf(ClientPhoto::class, $cliente->photos->first());
    }

    public function test_admin_can_upload_client_photos_through_the_relation(): void
    {
        Storage::fake('public');
        $cliente = $this->cliente('Subida', '9511313131');

        $this->actingAs($this->admin())
            ->post(route('clients.photos.store', $cliente), [
                'photos' => [UploadedFile::fake()->image('golpe.jpg')],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('client_photos', ['client_id' => $cliente->id]);
        $this->assertCount(1, $cliente->fresh()->photos);
    }

    public function test_clientes_importar_is_not_captured_by_the_show_route(): void
    {
        $this->actingAs($this->admin())
            ->get('/clientes/importar')
            ->assertOk();
    }

    public function test_gate_view_matches_policy_for_mechanic(): void
    {
        $cliente = $this->cliente('Gate', '9512424242');

        $this->actingAs($this->mecanico());
        $this->assertTrue(Gate::forUser($this->admin())->allows('view', $cliente));
        $this->assertTrue(Gate::forUser($this->mecanico())->denies('view', $cliente));
    }
}
