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
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * UI-10 · Fase 2: catálogo de servicios (CRUD admin/owner), semilla idempotente y protección de borrado.
 */
class ServicesUi10Test extends TestCase
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

    private function servicio(string $nombre = 'Servicio X', float $monto = 50, bool $activo = true): Service
    {
        return Service::create(['name' => $nombre, 'commission_amount' => $monto, 'is_active' => $activo]);
    }

    private function ejecutor(): Mechanic
    {
        return Mechanic::create([
            'name' => 'Ejecutor',
            'gender_id' => Gender::firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::firstOrCreate(['name' => 'General'])->id,
        ]);
    }

    // ── Semilla ───────────────────────────────────────────────────────────

    public function test_semilla_crea_los_tres_servicios_iniciales_con_sus_montos(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertSame('50.00', (string) Service::where('name', 'Afinación 4 cilindros')->value('commission_amount'));
        $this->assertSame('100.00', (string) Service::where('name', 'Afinación 6 cilindros')->value('commission_amount'));
        $this->assertSame('100.00', (string) Service::where('name', 'Afinación 8 cilindros sin múltiple')->value('commission_amount'));
    }

    public function test_semilla_es_idempotente_y_no_sobrescribe_comisiones_cambiadas(): void
    {
        $this->seed(CatalogSeeder::class);
        Service::where('name', 'Afinación 4 cilindros')->update(['commission_amount' => 70]);
        $total = Service::count();

        $this->seed(CatalogSeeder::class);

        $this->assertSame($total, Service::count());
        $this->assertSame('70.00', (string) Service::where('name', 'Afinación 4 cilindros')->value('commission_amount'));
    }

    // ── Acceso ────────────────────────────────────────────────────────────

    public function test_admin_y_owner_acceden_al_catalogo(): void
    {
        $this->servicio();

        $this->actingAs($this->admin())->get(route('services.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Services/Index')
                ->has('services', 1));

        $this->actingAs($this->owner())->get(route('services.index'))->assertOk();
        $this->actingAs($this->owner())->get(route('services.create'))->assertOk();
    }

    public function test_mecanico_recibe_403_en_todo_el_catalogo(): void
    {
        $user = $this->mecanico();
        $servicio = $this->servicio();

        $this->actingAs($user)->get(route('services.index'))->assertForbidden();
        $this->actingAs($user)->get(route('services.create'))->assertForbidden();
        $this->actingAs($user)->get(route('services.edit', $servicio))->assertForbidden();
        $this->actingAs($user)->post(route('services.store'), ['name' => 'Nuevo', 'commission_amount' => 10])->assertForbidden();
        $this->actingAs($user)->put(route('services.update', $servicio), ['name' => 'X', 'commission_amount' => 10])->assertForbidden();
        $this->actingAs($user)->delete(route('services.destroy', $servicio))->assertForbidden();
        $this->assertDatabaseHas('services', ['id' => $servicio->id, 'name' => 'Servicio X']);
    }

    // ── Alta y edición ────────────────────────────────────────────────────

    public function test_admin_crea_servicio_activo_por_defecto(): void
    {
        $this->actingAs($this->admin())->post(route('services.store'), [
            'name' => 'Cambio de clutch', 'commission_amount' => 300,
        ])->assertRedirect(route('services.index'));

        $this->assertDatabaseHas('services', ['name' => 'Cambio de clutch', 'is_active' => true]);
        $this->assertSame('300.00', (string) Service::where('name', 'Cambio de clutch')->value('commission_amount'));
    }

    public function test_comision_negativa_se_rechaza(): void
    {
        $this->actingAs($this->admin())->post(route('services.store'), [
            'name' => 'Negativo', 'commission_amount' => -1,
        ])->assertSessionHasErrors('commission_amount');

        $this->assertDatabaseMissing('services', ['name' => 'Negativo']);
    }

    public function test_nombre_es_unico(): void
    {
        $this->servicio('Duplicado');

        $this->actingAs($this->admin())->post(route('services.store'), [
            'name' => 'Duplicado', 'commission_amount' => 10,
        ])->assertSessionHasErrors('name');

        $this->assertSame(1, Service::where('name', 'Duplicado')->count());
    }

    public function test_admin_edita_comision_y_estado_del_servicio(): void
    {
        $servicio = $this->servicio('Editable', 50, true);

        $this->actingAs($this->admin())->put(route('services.update', $servicio), [
            'name' => 'Editable', 'commission_amount' => 80, 'is_active' => false,
        ])->assertRedirect(route('services.index'));

        $servicio->refresh();
        $this->assertSame('80.00', (string) $servicio->commission_amount);
        $this->assertFalse($servicio->is_active);
    }

    /** Asignación interna para pruebas: commission_model y commission_amount_snapshot no son fillable. */
    private function crearConcepto(array $datos): RepairOrderItem
    {
        $item = new RepairOrderItem();
        $item->forceFill($datos)->save();

        return $item;
    }

    // ── Borrado ───────────────────────────────────────────────────────────

    public function test_servicio_sin_conceptos_se_elimina(): void
    {
        $servicio = $this->servicio('Libre');

        $this->actingAs($this->admin())->delete(route('services.destroy', $servicio))
            ->assertRedirect(route('services.index'));

        $this->assertDatabaseMissing('services', ['id' => $servicio->id]);
    }

    public function test_servicio_usado_en_un_concepto_no_se_elimina(): void
    {
        $servicio = $this->servicio('Usado');
        $orden = RepairOrder::factory()->create();
        $this->crearConcepto([
            'repair_order_id' => $orden->id, 'type' => 'labor', 'description' => 'X',
            'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100,
            'service_id' => $servicio->id, 'mechanic_id' => $this->ejecutor()->id,
            'commission_model' => RepairOrderItem::MODELO_FIXED, 'commission_amount_snapshot' => 50,
        ]);

        $this->actingAs($this->admin())->delete(route('services.destroy', $servicio))
            ->assertSessionHasErrors('service');

        $this->assertDatabaseHas('services', ['id' => $servicio->id]);
    }

    public function test_servicio_en_uso_se_muestra_como_en_uso_en_el_listado(): void
    {
        $servicio = $this->servicio('Marcado');
        $orden = RepairOrder::factory()->create();
        $this->crearConcepto([
            'repair_order_id' => $orden->id, 'type' => 'labor', 'description' => 'X',
            'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100,
            'service_id' => $servicio->id, 'mechanic_id' => $this->ejecutor()->id,
            'commission_model' => RepairOrderItem::MODELO_FIXED, 'commission_amount_snapshot' => 50,
        ]);

        $this->actingAs($this->admin())->get(route('services.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('services.0.en_uso', true));
    }

    // ── Personal: no borrar mecánico con mano de obra ─────────────────────

    public function test_no_se_elimina_mecanico_con_conceptos_de_mano_de_obra(): void
    {
        $mec = $this->ejecutor();
        $orden = RepairOrder::factory()->create();
        $this->crearConcepto([
            'repair_order_id' => $orden->id, 'type' => 'labor', 'description' => 'X',
            'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100,
            'mechanic_id' => $mec->id,
            'commission_model' => RepairOrderItem::MODELO_FIXED, 'commission_amount_snapshot' => 0,
        ]);

        $this->actingAs($this->admin())->delete(route('mechanics.destroy', $mec))
            ->assertSessionHasErrors('mechanic');

        $this->assertDatabaseHas('mechanics', ['id' => $mec->id]);
    }

}
