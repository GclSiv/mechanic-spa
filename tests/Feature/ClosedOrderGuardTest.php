<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FollowUp;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * IMPLEMENTACIÓN-UI-4B (cierre de brecha backend): una orden cerrada (entregado/rechazado)
 * es de solo lectura para TODOS los roles en diagnóstico y en bitácora.
 */
class ClosedOrderGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Mechanic} */
    private function mechanic(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $mechanic = Mechanic::create([
            'name'             => 'Mecánico cierre',
            'user_id'          => $user->id,
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        return [$user, $mechanic];
    }

    private function estado(string $slug): RepairOrderStatus
    {
        return RepairOrderStatus::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'color_class' => 'bg-gray-500'],
        );
    }

    private function orden(string $slug, ?Mechanic $mechanic, bool $needsQuote = false): RepairOrder
    {
        $client = Client::factory()->create(['rfc' => 'XAXX010101000']);
        $recepcion = Recepcion::factory()->create(['client_id' => $client->id]);

        return RepairOrder::factory()->create([
            'recepcion_id' => $recepcion->id,
            'client_id'    => $client->id,
            'vehicle_id'   => $recepcion->vehicle_id,
            'mechanic_id'  => $mechanic?->id,
            'status_id'    => $this->estado($slug)->id,
            'needs_quote'  => $needsQuote,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    // ── Diagnóstico: orden cerrada bloqueada para todos los roles ─────────

    public function test_admin_no_puede_registrar_diagnostico_en_orden_entregada(): void
    {
        [, $mec] = $this->mechanic();
        $orden = $this->orden('entregado', $mec);

        $this->actingAs($this->admin())
            ->post(route('repair-orders.diagnosis.complete', $orden))
            ->assertForbidden();

        $orden->refresh();
        $this->assertFalse((bool) $orden->needs_quote);
        $this->assertSame('entregado', $orden->status->slug);
        $this->assertSame(0, FollowUp::count());
    }

    public function test_admin_no_puede_registrar_diagnostico_en_orden_rechazada_ni_genera_nota(): void
    {
        $orden = $this->orden('rechazado', null);

        $this->actingAs($this->admin())
            ->post(route('repair-orders.diagnosis.complete', $orden))
            ->assertForbidden();

        $orden->refresh();
        $this->assertFalse((bool) $orden->needs_quote);
        $this->assertSame('rechazado', $orden->status->slug);
        $this->assertSame(0, FollowUp::count());
    }

    public function test_mecanico_asignado_no_puede_registrar_diagnostico_en_orden_entregada(): void
    {
        [$user, $mec] = $this->mechanic();
        $orden = $this->orden('entregado', $mec);

        $this->actingAs($user)
            ->post(route('repair-orders.diagnosis.complete', $orden))
            ->assertForbidden();

        $orden->refresh();
        $this->assertFalse((bool) $orden->needs_quote);
        $this->assertSame('entregado', $orden->status->slug);
        $this->assertSame(0, FollowUp::count());
    }

    public function test_diagnostico_no_modifica_needs_quote_ya_existente_en_orden_cerrada(): void
    {
        [$user, $mec] = $this->mechanic();
        $orden = $this->orden('entregado', $mec, needsQuote: true);

        $this->actingAs($user)
            ->post(route('repair-orders.diagnosis.complete', $orden))
            ->assertForbidden();

        $this->assertTrue((bool) $orden->refresh()->needs_quote);
        $this->assertSame(0, FollowUp::count());
    }

    public function test_orden_abierta_conserva_el_diagnostico_del_mecanico(): void
    {
        [$user, $mec] = $this->mechanic();
        $this->estado('diagnostico'); // destino del cambio de estado; las migraciones no lo siembran
        $orden = $this->orden('recibido', $mec);

        $this->actingAs($user)
            ->post(route('repair-orders.diagnosis.complete', $orden))
            ->assertRedirect();

        $orden->refresh();
        $this->assertTrue((bool) $orden->needs_quote);
        $this->assertSame('diagnostico', $orden->status->slug);
        $this->assertSame(1, FollowUp::count());
    }

    // ── Bitácora: orden cerrada sin notas nuevas para todos los roles ─────

    public function test_admin_no_puede_agregar_nota_en_orden_entregada(): void
    {
        [, $mec] = $this->mechanic();
        $orden = $this->orden('entregado', $mec);

        $this->actingAs($this->admin())
            ->post(route('repair-orders.follow-ups.store', $orden), [
                'mechanic_id' => $mec->id,
                'description' => 'Nota tardía',
                'date'        => '2026-11-22',
            ])
            ->assertForbidden();

        $this->assertSame(0, FollowUp::count());
    }

    public function test_admin_no_puede_agregar_nota_en_orden_rechazada(): void
    {
        $orden = $this->orden('rechazado', null);

        $this->actingAs($this->admin())
            ->post(route('repair-orders.follow-ups.store', $orden), [
                'mechanic_id' => null,
                'description' => 'Nota tardía',
                'date'        => '2026-11-22',
            ])
            ->assertForbidden();

        $this->assertSame(0, FollowUp::count());
    }

    public function test_mecanico_asignado_no_puede_agregar_nota_en_orden_entregada(): void
    {
        [$user, $mec] = $this->mechanic();
        $orden = $this->orden('entregado', $mec);

        $this->actingAs($user)
            ->post(route('repair-orders.follow-ups.store', $orden), [
                'description' => 'Nota tardía',
                'date'        => '2026-11-22',
            ])
            ->assertForbidden();

        $this->assertSame(0, FollowUp::count());
    }

    public function test_orden_abierta_sigue_aceptando_notas(): void
    {
        [$user, $mec] = $this->mechanic();
        $orden = $this->orden('en-reparacion', $mec);

        $this->actingAs($user)
            ->post(route('repair-orders.follow-ups.store', $orden), [
                'description' => 'Avance real',
                'date'        => '2026-11-22',
            ])
            ->assertRedirect();

        $this->assertSame(1, FollowUp::count());
    }

    /** @return array<string, array{0: string}> */
    public static function estadosCerrados(): array
    {
        return [
            'entregado' => ['entregado'],
            'rechazado' => ['rechazado'],
        ];
    }

    #[DataProvider('estadosCerrados')]
    public function test_nadie_asigna_ni_reasigna_mecanico_en_orden_cerrada(string $slug): void
    {
        [, $actual] = $this->mechanic();
        [, $otro] = $this->mechanic();
        $orden = $this->orden($slug, $actual);

        $this->actingAs($this->admin())
            ->patch(route('repair-orders.mechanic.assign', $orden), ['mechanic_id' => $otro->id])
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->post(route('repair-orders.mechanic.reassign', $orden), [
                'mechanic_id' => $otro->id,
                'reason'      => 'Cambio de responsable',
            ])
            ->assertForbidden();

        $this->assertSame($actual->id, (int) $orden->refresh()->mechanic_id);
        $this->assertSame(0, FollowUp::count());
    }

    #[DataProvider('estadosCerrados')]
    public function test_nadie_elimina_notas_de_orden_cerrada_y_la_nota_permanece(string $slug): void
    {
        [$user, $mec] = $this->mechanic();
        $orden = $this->orden($slug, $mec);
        $nota = FollowUp::create([
            'repair_order_id' => $orden->id,
            'mechanic_id'     => $mec->id,
            'description'     => 'Histórica',
            'date'            => '2026-11-22',
        ]);

        $this->actingAs($this->admin())
            ->delete(route('repair-orders.follow-ups.destroy', [$orden, $nota]))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('repair-orders.follow-ups.destroy', [$orden, $nota]))
            ->assertForbidden();

        $this->assertTrue(FollowUp::whereKey($nota->id)->exists());
    }

    public function test_admin_asigna_y_reasigna_mecanico_en_orden_abierta(): void
    {
        [, $mec] = $this->mechanic();
        [, $otro] = $this->mechanic();
        $orden = $this->orden('en-reparacion', $mec);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('repair-orders.mechanic.assign', $orden), ['mechanic_id' => $otro->id])
            ->assertRedirect();
        $this->assertSame($otro->id, (int) $orden->refresh()->mechanic_id);

        $this->actingAs($admin)
            ->post(route('repair-orders.mechanic.reassign', $orden), [
                'mechanic_id' => $mec->id,
                'reason'      => 'Vuelve al taller',
            ])
            ->assertRedirect();
        $this->assertSame($mec->id, (int) $orden->refresh()->mechanic_id);
        $this->assertSame(1, FollowUp::count());
    }
}
