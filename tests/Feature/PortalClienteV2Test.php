<?php

namespace Tests\Feature;

use App\Models\AppointmentRequest;
use App\Models\Client;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\ServiceReminder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Portal del cliente V2: contacto, solicitud de cita y próximo servicio. */
class PortalClienteV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role' => $rol]);
    }

    private function mecanico(User $usuario): Mechanic
    {
        return Mechanic::create([
            'name' => 'Mec ' . $usuario->id,
            'user_id' => $usuario->id,
            'gender_id' => Gender::firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::firstOrCreate(['name' => 'General'])->id,
        ]);
    }

    /** Orden con recepción, cliente y vehículo propios. */
    private function orden(?Mechanic $mecanico = null): RepairOrder
    {
        $status = RepairOrderStatus::query()->firstOrCreate(
            ['slug' => 'diagnostico'],
            ['name' => 'diagnostico', 'color_class' => 'bg-gray-500']
        );
        $cliente = Client::factory()->create();
        $vehiculo = Vehicle::factory()->create(['client_id' => $cliente->id]);
        $recepcion = Recepcion::create([
            'client_id' => $cliente->id, 'vehicle_id' => $vehiculo->id, 'fuel_level' => '1/2',
        ]);

        return RepairOrder::factory()->create([
            'recepcion_id' => $recepcion->id, 'tax_rate' => 16, 'status_id' => $status->id,
            'mechanic_id' => $mecanico?->id,
        ]);
    }

    private function citaValida(RepairOrder $orden, array $extra = []): array
    {
        return array_merge([
            'reason' => 'mantenimiento',
            'preferred_date' => now()->addDays(5)->toDateString(),
            'preferred_time' => 'manana',
            'notes' => 'Prefiero por la mañana',
        ], $extra);
    }

    // ── Seguimiento: CTA y solicitud ──────────────────────────────────────

    public function test_token_valido_muestra_datos_de_contacto_y_cita(): void
    {
        $orden = $this->orden();
        \App\Models\Setting::current()->update(['whatsapp' => '9511112233', 'phone' => '9510000000']);

        $this->get(route('tracking.show', $orden->tracking_token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Tracking/Show')
                ->where('settings.whatsapp', '9511112233')
                ->where('cita', null)
                ->has('recordatorios', 0));
    }

    public function test_token_invalido_no_accede_ni_crea_cita(): void
    {
        $this->orden();

        $this->get('/seguimiento/' . str_repeat('x', 48))->assertNotFound();
        $this->post('/seguimiento/' . str_repeat('x', 48) . '/citas', [])->assertNotFound();
        $this->assertDatabaseCount('appointment_requests', 0);
    }

    public function test_solicitud_publica_crea_pendiente_con_relaciones_del_backend(): void
    {
        $orden = $this->orden();
        $otroCliente = Client::factory()->create();
        $otroVehiculo = Vehicle::factory()->create(['client_id' => $otroCliente->id]);

        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden, [
            'client_id' => $otroCliente->id,
            'vehicle_id' => $otroVehiculo->id,
            'repair_order_id' => 999,
            'status' => AppointmentRequest::CONFIRMADA,
            'confirmed_date' => now()->addDay()->toDateString(),
            'admin_notes' => 'nota forzada',
        ]))->assertRedirect();

        $cita = AppointmentRequest::firstOrFail();
        $this->assertSame($orden->recepcion->client_id, $cita->client_id);
        $this->assertSame($orden->recepcion->vehicle_id, $cita->vehicle_id);
        $this->assertSame($orden->id, $cita->repair_order_id);
        $this->assertSame(AppointmentRequest::PENDIENTE, $cita->status);
        $this->assertNull($cita->confirmed_date);
        $this->assertNull($cita->admin_notes);
    }

    public function test_solicitud_rechaza_datos_invalidos(): void
    {
        $orden = $this->orden();

        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden, [
            'reason' => 'cualquiera',
        ]))->assertSessionHasErrors('reason');

        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden, [
            'preferred_date' => now()->subDays(3)->toDateString(),
        ]))->assertSessionHasErrors('preferred_date');

        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden, [
            'preferred_date' => now()->addYears(2)->toDateString(),
        ]))->assertSessionHasErrors('preferred_date');

        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden, [
            'notes' => str_repeat('a', 501),
        ]))->assertSessionHasErrors('notes');

        $this->assertDatabaseCount('appointment_requests', 0);
    }

    public function test_solicitud_publica_tiene_limite_de_peticiones(): void
    {
        $orden = $this->orden();

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden));
        }

        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden))
            ->assertStatus(429);
    }

    public function test_cita_confirmada_muestra_nota_del_taller_sin_notas_internas(): void
    {
        $orden = $this->orden();
        $admin = $this->usuario(User::ROLE_ADMIN);
        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden));
        $cita = AppointmentRequest::firstOrFail();

        $this->actingAs($admin)->patch(route('citas.update', $cita), [
            'accion' => 'confirmar',
            'confirmed_date' => now()->addDays(6)->toDateString(),
            'confirmed_time' => '09:30',
            'customer_note' => 'Llega a las 9:30',
            'admin_notes' => 'Interno: cliente pide descuento',
        ])->assertRedirect();

        $this->get(route('tracking.show', $orden->tracking_token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cita.estado', 'confirmada')
                ->where('cita.nota_taller', 'Llega a las 9:30')
                ->where('cita.confirmada_hora', '09:30')
                ->missing('cita.admin_notes'));
    }

    // ── Administración de citas ───────────────────────────────────────────

    public function test_admin_y_owner_administran_citas(): void
    {
        $orden = $this->orden();
        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden));
        $cita = AppointmentRequest::firstOrFail();

        $this->actingAs($this->usuario(User::ROLE_ADMIN))->get(route('citas.index'))->assertOk();
        $this->actingAs($this->usuario(User::ROLE_OWNER))->get(route('citas.index'))->assertOk();

        $this->actingAs($this->usuario(User::ROLE_OWNER))->patch(route('citas.update', $cita), [
            'accion' => 'confirmar',
            'confirmed_date' => now()->addDays(4)->toDateString(),
            'confirmed_time' => '10:00',
        ])->assertRedirect();

        $cita->refresh();
        $this->assertSame(AppointmentRequest::CONFIRMADA, $cita->status);
        $this->assertSame('10:00', $cita->confirmed_time);
        $this->assertSame(now()->addDays(4)->toDateString(), $cita->confirmed_date->toDateString());
    }

    public function test_mecanico_recibe_403_en_citas(): void
    {
        $orden = $this->orden();
        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden));
        $cita = AppointmentRequest::firstOrFail();
        $mecanico = $this->usuario(User::ROLE_MECHANIC);
        $this->mecanico($mecanico);

        $this->actingAs($mecanico)->get(route('citas.index'))->assertForbidden();
        $this->actingAs($mecanico)->patch(route('citas.update', $cita), [
            'accion' => 'confirmar',
            'confirmed_date' => now()->addDays(4)->toDateString(),
            'confirmed_time' => '10:00',
        ])->assertForbidden();

        $this->assertSame(AppointmentRequest::PENDIENTE, $cita->refresh()->status);
    }

    public function test_invitado_no_administra_citas(): void
    {
        $orden = $this->orden();
        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden));
        $cita = AppointmentRequest::firstOrFail();

        $this->get(route('citas.index'))->assertRedirectContains('login');
        $this->patch(route('citas.update', $cita), ['accion' => 'rechazar'])->assertRedirectContains('login');
    }

    public function test_transicion_no_permitida_se_rechaza(): void
    {
        $orden = $this->orden();
        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden));
        $cita = AppointmentRequest::firstOrFail();
        $cita->update(['status' => AppointmentRequest::RECHAZADA]);

        $this->actingAs($this->usuario(User::ROLE_ADMIN))->patch(route('citas.update', $cita), [
            'accion' => 'confirmar',
            'confirmed_date' => now()->addDays(4)->toDateString(),
            'confirmed_time' => '10:00',
        ])->assertStatus(422);

        $this->assertSame(AppointmentRequest::RECHAZADA, $cita->refresh()->status);
    }

    // ── Próximo servicio ──────────────────────────────────────────────────

    public function test_admin_programa_proximo_servicio_y_cliente_lo_ve(): void
    {
        $orden = $this->orden();

        $this->actingAs($this->usuario(User::ROLE_ADMIN))
            ->post(route('repair-orders.recordatorios.store', $orden), [
                'title' => 'Cambio de aceite',
                'recommended_date' => now()->addMonths(6)->toDateString(),
                'recommended_mileage' => 160000,
                'notes' => 'Usar aceite sintético',
            ])->assertRedirect();

        $this->assertDatabaseHas('service_reminders', [
            'vehicle_id' => $orden->recepcion->vehicle_id,
            'client_id' => $orden->recepcion->client_id,
            'status' => ServiceReminder::PENDIENTE,
            'recommended_mileage' => 160000,
        ]);

        $this->get(route('tracking.show', $orden->tracking_token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('recordatorios', 1)
                ->where('recordatorios.0.titulo', 'Cambio de aceite')
                ->where('recordatorios.0.kilometraje', 160000));
    }

    public function test_recordatorio_exige_fecha_o_kilometraje(): void
    {
        $orden = $this->orden();

        $this->actingAs($this->usuario(User::ROLE_ADMIN))
            ->post(route('repair-orders.recordatorios.store', $orden), ['title' => 'Sin criterio'])
            ->assertStatus(422);

        $this->assertDatabaseCount('service_reminders', 0);
    }

    public function test_mecanico_no_programa_proximo_servicio(): void
    {
        $orden = $this->orden();
        $mecanico = $this->usuario(User::ROLE_MECHANIC);
        $this->mecanico($mecanico);

        $this->actingAs($mecanico)
            ->post(route('repair-orders.recordatorios.store', $orden), [
                'title' => 'Intento',
                'recommended_mileage' => 1000,
            ])->assertForbidden();

        $this->assertDatabaseCount('service_reminders', 0);
    }

    public function test_seguimiento_no_muestra_recordatorios_de_otro_vehiculo(): void
    {
        $miOrden = $this->orden();
        $otraOrden = $this->orden();
        ServiceReminder::create([
            'client_id' => $otraOrden->recepcion->client_id,
            'vehicle_id' => $otraOrden->recepcion->vehicle_id,
            'repair_order_id' => $otraOrden->id,
            'title' => 'Ajeno',
            'recommended_mileage' => 5000,
            'status' => ServiceReminder::PENDIENTE,
        ]);

        $this->get(route('tracking.show', $miOrden->tracking_token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('recordatorios', 0)
                ->missing('recordatorios.0.titulo'));
    }

    // ── Solicitudes activas duplicadas ────────────────────────────────────

    public function test_cita_pendiente_bloquea_segunda_solicitud_del_mismo_vehiculo(): void
    {
        $orden = $this->orden();

        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden))
            ->assertRedirect();
        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden, [
            'reason' => 'revision',
        ]))->assertSessionHasErrors('cita');

        $this->assertDatabaseCount('appointment_requests', 1);
        $this->assertSame(1, AppointmentRequest::activas()->where('vehicle_id', $orden->recepcion->vehicle_id)->count());
        $this->assertSame('mantenimiento', AppointmentRequest::firstOrFail()->reason);
    }

    public function test_cita_confirmada_bloquea_nueva_solicitud(): void
    {
        $orden = $this->orden();
        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden));
        $cita = AppointmentRequest::firstOrFail();

        $this->actingAs($this->usuario(User::ROLE_ADMIN))->patch(route('citas.update', $cita), [
            'accion' => 'confirmar',
            'confirmed_date' => now()->addDays(4)->toDateString(),
            'confirmed_time' => '10:30',
        ])->assertRedirect();

        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden))
            ->assertSessionHasErrors('cita');

        $this->assertDatabaseCount('appointment_requests', 1);
    }

    public function test_otra_orden_del_mismo_vehiculo_tampoco_crea_duplicado(): void
    {
        $orden = $this->orden();
        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden));

        $otraRecepcion = Recepcion::create([
            'client_id' => $orden->recepcion->client_id,
            'vehicle_id' => $orden->recepcion->vehicle_id,
            'fuel_level' => '1/2',
        ]);
        $otraOrden = RepairOrder::factory()->create([
            'recepcion_id' => $otraRecepcion->id, 'tax_rate' => 16, 'status_id' => $orden->status_id,
        ]);

        $this->post(route('tracking.appointment.store', $otraOrden->tracking_token), $this->citaValida($otraOrden))
            ->assertSessionHasErrors('cita');

        $this->assertDatabaseCount('appointment_requests', 1);
    }

    public function test_seguimiento_muestra_la_cita_activa_tras_un_duplicado(): void
    {
        $orden = $this->orden();
        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden));
        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden));

        $this->get(route('tracking.show', $orden->tracking_token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cita.activa', true)
                ->where('cita.estado', AppointmentRequest::PENDIENTE)
                ->missing('cita.admin_notes'));
    }

    public static function estadosFinalizados(): array
    {
        return [
            'cancelada' => [AppointmentRequest::CANCELADA],
            'rechazada' => [AppointmentRequest::RECHAZADA],
            'completada' => [AppointmentRequest::COMPLETADA],
        ];
    }

    #[DataProvider('estadosFinalizados')]
    public function test_estado_final_permite_una_nueva_solicitud(string $estado): void
    {
        $orden = $this->orden();
        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden));
        AppointmentRequest::firstOrFail()->update(['status' => $estado]);

        $this->post(route('tracking.appointment.store', $orden->tracking_token), $this->citaValida($orden))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('appointment_requests', 2);
        $this->assertSame(1, AppointmentRequest::activas()->count());
    }

    // ── Navegación ────────────────────────────────────────────────────────

    public function test_navegacion_expone_citas_solo_a_owner_y_admin(): void
    {
        $this->actingAs($this->usuario(User::ROLE_ADMIN))
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.isAdmin', true));

        $this->actingAs($this->usuario(User::ROLE_OWNER))
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.isAdmin', true)->where('auth.isOwner', true));

        $mecanico = $this->usuario(User::ROLE_MECHANIC);
        $this->mecanico($mecanico);
        $this->actingAs($mecanico)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.isAdmin', false)->where('auth.isOwner', false));
    }
}
