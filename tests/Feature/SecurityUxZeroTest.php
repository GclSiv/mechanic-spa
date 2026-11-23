<?php

namespace Tests\Feature;

use App\Models\FollowUp;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\Part;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderPhoto;
use App\Models\RepairOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * SECURITY-UX-0: hallazgos de seguridad y autorización detectados antes del rediseño UX/UI.
 * La protección vive en backend; estas pruebas no dependen de la interfaz.
 */
class SecurityUxZeroTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────

    /** @return array{0: User, 1: Mechanic} */
    private function mechanic(string $nombre = 'Mecánico de prueba'): array
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $mechanic = Mechanic::create([
            'name'             => $nombre,
            'user_id'          => $user->id,
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        return [$user, $mechanic];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER]);
    }

    private function estado(string $slug): RepairOrderStatus
    {
        return RepairOrderStatus::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'color_class' => 'bg-gray-500']
        );
    }

    private function orderIn(string $slug, ?Mechanic $mechanic = null): RepairOrder
    {
        return RepairOrder::factory()->create([
            'status_id'   => $this->estado($slug)->id,
            'mechanic_id' => $mechanic?->id,
        ]);
    }

    private function part(): Part
    {
        return Part::create([
            'name'                => 'Balata delantera',
            'sku'                 => 'BAL-' . uniqid(),
            'cost_price'          => 80,
            'sale_price'          => 150,
            'stock'               => 5,
            'low_stock_threshold' => 1,
        ]);
    }

    private function datosMecanico(string $email, string $role, string $password = 'Clave-Inicial-2026'): array
    {
        return [
            'name'             => 'Nuevo Mecánico',
            'email'            => $email,
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
            'phone'            => '9511234567',
            'commission_rate'  => 10,
            'role'             => $role,
            'password'         => $password,
            'password_confirmation' => $password,
        ];
    }

    // ── SECURITY-1: contraseña aleatoria, nunca fija ni visible ───────────

    public function test_alta_de_mecanico_no_usa_contrasena_fija(): void
    {
        $this->actingAs($this->admin())
            ->post(route('mechanics.store'), $this->datosMecanico('nuevo@taller.test', 'mechanic'))
            ->assertRedirect(route('mechanics.index'));

        $user = User::where('email', 'nuevo@taller.test')->firstOrFail();

        $this->assertFalse(Hash::check('password123', $user->password));
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertTrue(Hash::isHashed($user->password));
        $this->assertNotSame('password123', $user->password);
    }

    public function test_mensaje_de_exito_no_contiene_contrasena(): void
    {
        $this->actingAs($this->admin())
            ->post(route('mechanics.store'), $this->datosMecanico('visible@taller.test', 'mechanic'))
            ->assertSessionHas('success', function (string $mensaje) {
                return ! str_contains($mensaje, 'password123')
                    && ! str_contains($mensaje, 'visible@taller.test')
                    && ! str_contains($mensaje, '/');
            });
    }

    public function test_alta_conserva_el_rol_elegido(): void
    {
        $this->actingAs($this->admin())
            ->post(route('mechanics.store'), $this->datosMecanico('admin-nuevo@taller.test', 'admin'));
        $this->actingAs($this->admin())
            ->post(route('mechanics.store'), $this->datosMecanico('mec-nuevo@taller.test', 'mechanic'));

        $this->assertSame('admin', User::where('email', 'admin-nuevo@taller.test')->value('role'));
        $this->assertSame('mechanic', User::where('email', 'mec-nuevo@taller.test')->value('role'));
    }

    public function test_actualizar_mecanico_sin_cuenta_no_expone_ni_usa_contrasena_fija(): void
    {
        $mecanico = Mechanic::create([
            'name'             => 'Legado',
            'email'            => 'legado@taller.test',
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        $this->actingAs($this->admin())
            ->put(route('mechanics.update', $mecanico), $this->datosMecanico('legado@taller.test', 'mechanic'))
            ->assertSessionHas('success', fn (string $m) => ! str_contains($m, 'password123'));

        $user = User::where('email', 'legado@taller.test')->firstOrFail();
        $this->assertFalse(Hash::check('password123', $user->password));
        $this->assertTrue(Hash::isHashed($user->password));
    }

    // ── SECURITY-2: listado y exportación de cotizaciones solo admin/owner ─

    public function test_admin_y_owner_acceden_al_listado_de_cotizaciones(): void
    {
        $this->actingAs($this->admin())->get(route('quotes.follow-up'))->assertOk();
        $this->actingAs($this->owner())->get(route('quotes.follow-up'))->assertOk();
    }

    public function test_mecanico_recibe_403_en_listado_de_cotizaciones(): void
    {
        [$user] = $this->mechanic();

        $this->actingAs($user)->get(route('quotes.follow-up'))->assertForbidden();
    }

    public function test_mecanico_no_puede_exportar_cotizaciones(): void
    {
        [$user] = $this->mechanic();

        $this->actingAs($user)->get(route('quotes.follow-up.export'))->assertForbidden();
    }

    public function test_admin_y_owner_pueden_exportar_cotizaciones(): void
    {
        $this->actingAs($this->admin())->get(route('quotes.follow-up.export'))->assertOk();
        $this->actingAs($this->owner())->get(route('quotes.follow-up.export'))->assertOk();
    }

    // ── SECURITY-3: bitácora atribuida a la identidad autenticada ─────────

    public function test_mecanico_no_puede_suplantar_a_otro_mecanico_en_bitacora(): void
    {
        [$user, $mecanico] = $this->mechanic('Carlos');
        [, $otro] = $this->mechanic('Miguel');
        $orden = $this->orderIn('en-reparacion', $mecanico);

        $this->actingAs($user)
            ->post(route('repair-orders.follow-ups.store', $orden), [
                'mechanic_id' => $otro->id,
                'description' => 'Nota falsa',
                'date'        => '2026-11-22',
            ])
            ->assertForbidden();

        $this->assertSame(0, FollowUp::count());
    }

    public function test_bitacora_del_mecanico_se_atribuye_a_si_mismo_sin_enviar_mechanic_id(): void
    {
        [$user, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('en-reparacion', $mecanico);

        $this->actingAs($user)
            ->post(route('repair-orders.follow-ups.store', $orden), [
                'description' => 'Avance real',
                'date'        => '2026-11-22',
            ])
            ->assertRedirect();

        $this->assertSame($mecanico->id, (int) FollowUp::firstOrFail()->mechanic_id);
    }

    public function test_mecanico_no_puede_agregar_bitacora_a_orden_ajena(): void
    {
        [$user] = $this->mechanic();
        [, $otro] = $this->mechanic('Otro');
        $ajena = $this->orderIn('en-reparacion', $otro);

        $this->actingAs($user)
            ->post(route('repair-orders.follow-ups.store', $ajena), [
                'description' => 'Intento',
                'date'        => '2026-11-22',
            ])
            ->assertForbidden();

        $this->assertSame(0, FollowUp::count());
    }

    public function test_admin_conserva_la_seleccion_de_mecanico_en_bitacora(): void
    {
        [, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('en-reparacion', $mecanico);

        $this->actingAs($this->admin())
            ->post(route('repair-orders.follow-ups.store', $orden), [
                'mechanic_id' => $mecanico->id,
                'description' => 'Nota del administrador',
                'date'        => '2026-11-22',
            ])
            ->assertRedirect();

        $this->assertSame($mecanico->id, (int) FollowUp::firstOrFail()->mechanic_id);
    }

    // ── SECURITY-4: refacciones solo en órdenes abiertas ──────────────────

    public function test_mecanico_agrega_refaccion_en_orden_abierta_a_precio_de_catalogo(): void
    {
        [$user, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('en-reparacion', $mecanico);
        $parte = $this->part();

        $this->actingAs($user)
            ->post(route('repair-orders.items.store', $orden), [
                'type'       => 'part',
                'part_id'    => $parte->id,
                'quantity'   => 1,
                'unit_price' => 1,
            ])
            ->assertRedirect();

        $item = RepairOrderItem::where('repair_order_id', $orden->id)->firstOrFail();
        $this->assertSame(150.0, (float) $item->unit_price);
    }

    public function test_mecanico_no_agrega_refaccion_en_orden_entregada(): void
    {
        [$user, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('entregado', $mecanico);

        $this->actingAs($user)
            ->post(route('repair-orders.items.store', $orden), [
                'type' => 'part', 'part_id' => $this->part()->id, 'quantity' => 1, 'unit_price' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(0, RepairOrderItem::count());
    }

    public function test_mecanico_no_agrega_refaccion_en_orden_rechazada(): void
    {
        [$user, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('rechazado', $mecanico);

        $this->actingAs($user)
            ->post(route('repair-orders.items.store', $orden), [
                'type' => 'part', 'part_id' => $this->part()->id, 'quantity' => 1, 'unit_price' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(0, RepairOrderItem::count());
    }

    public function test_mecanico_no_elimina_refaccion_de_orden_cerrada(): void
    {
        [$user, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('entregado', $mecanico);
        $item = RepairOrderItem::factory()->create([
            'repair_order_id' => $orden->id, 'type' => 'part', 'quantity' => 1, 'unit_price' => 10, 'subtotal' => 10,
        ]);

        $this->actingAs($user)
            ->delete(route('repair-orders.items.destroy', [$orden, $item]))
            ->assertForbidden();

        $this->assertTrue(RepairOrderItem::whereKey($item->id)->exists());
    }

    public function test_mecanico_sigue_sin_poder_agregar_mano_de_obra(): void
    {
        [$user, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('en-reparacion', $mecanico);

        $this->actingAs($user)
            ->post(route('repair-orders.items.store', $orden), [
                'type' => 'labor', 'description' => 'Cambio', 'quantity' => 1, 'unit_price' => 500,
            ])
            ->assertForbidden();
    }

    public function test_mecanico_no_agrega_refaccion_a_orden_ajena(): void
    {
        [$user] = $this->mechanic();
        [, $otro] = $this->mechanic('Otro');
        $ajena = $this->orderIn('en-reparacion', $otro);

        $this->actingAs($user)
            ->post(route('repair-orders.items.store', $ajena), [
                'type' => 'part', 'part_id' => $this->part()->id, 'quantity' => 1, 'unit_price' => 1,
            ])
            ->assertForbidden();
    }

    public function test_admin_no_agrega_refaccion_en_orden_cerrada(): void
    {
        // Decisión de dominio UI-4C: una orden entregada/rechazada es histórica y de solo lectura para todos los roles.
        [, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('entregado', $mecanico);

        $this->actingAs($this->admin())
            ->post(route('repair-orders.items.store', $orden), [
                'type' => 'part', 'part_id' => $this->part()->id, 'quantity' => 1, 'unit_price' => 1,
            ])
            ->assertForbidden();
    }

    // ── SECURITY-5: evidencia no se elimina en órdenes cerradas ──────────

    public function test_mecanico_elimina_foto_de_avance_en_orden_abierta(): void
    {
        Storage::fake('public');
        [$user, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('en-reparacion', $mecanico);
        Storage::disk('public')->put('avance/prueba.jpg', 'x');
        $foto = RepairOrderPhoto::create(['repair_order_id' => $orden->id, 'path' => 'avance/prueba.jpg']);

        $this->actingAs($user)
            ->delete(route('repair-orders.avance.destroy', [$orden, $foto]))
            ->assertRedirect();

        $this->assertFalse(RepairOrderPhoto::whereKey($foto->id)->exists());
    }

    public function test_mecanico_no_elimina_foto_de_avance_en_orden_entregada(): void
    {
        Storage::fake('public');
        [$user, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('entregado', $mecanico);
        Storage::disk('public')->put('avance/historica.jpg', 'x');
        $foto = RepairOrderPhoto::create(['repair_order_id' => $orden->id, 'path' => 'avance/historica.jpg']);

        $this->actingAs($user)
            ->delete(route('repair-orders.avance.destroy', [$orden, $foto]))
            ->assertForbidden();

        $this->assertTrue(RepairOrderPhoto::whereKey($foto->id)->exists());
        Storage::disk('public')->assertExists('avance/historica.jpg');
    }

    public function test_mecanico_no_elimina_evidencia_de_bitacora_en_orden_rechazada(): void
    {
        Storage::fake('public');
        [$user, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('rechazado', $mecanico);
        $nota = FollowUp::create([
            'repair_order_id' => $orden->id,
            'mechanic_id'     => $mecanico->id,
            'description'     => 'Evidencia',
            'date'            => '2026-11-22',
            'evidence_photos' => ['follow_ups/evidencia.jpg'],
        ]);

        $this->actingAs($user)
            ->delete(route('repair-orders.follow-ups.destroy', [$orden, $nota]))
            ->assertForbidden();

        $this->assertTrue(FollowUp::whereKey($nota->id)->exists());
    }

    public function test_mecanico_no_elimina_evidencia_de_orden_ajena(): void
    {
        Storage::fake('public');
        [$user] = $this->mechanic();
        [, $otro] = $this->mechanic('Otro');
        $ajena = $this->orderIn('en-reparacion', $otro);
        Storage::disk('public')->put('avance/ajena.jpg', 'x');
        $foto = RepairOrderPhoto::create(['repair_order_id' => $ajena->id, 'path' => 'avance/ajena.jpg']);

        $this->actingAs($user)
            ->delete(route('repair-orders.avance.destroy', [$ajena, $foto]))
            ->assertForbidden();

        $this->assertTrue(RepairOrderPhoto::whereKey($foto->id)->exists());
    }

    public function test_admin_no_elimina_foto_en_orden_cerrada(): void
    {
        Storage::fake('public');
        [, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('entregado', $mecanico);
        Storage::disk('public')->put('avance/admin.jpg', 'x');
        $foto = RepairOrderPhoto::create(['repair_order_id' => $orden->id, 'path' => 'avance/admin.jpg']);

        // Regla de dominio: la evidencia de una orden cerrada es de solo lectura, también para admin.
        $this->actingAs($this->admin())
            ->delete(route('repair-orders.avance.destroy', [$orden, $foto]))
            ->assertForbidden();

        $this->assertTrue(RepairOrderPhoto::whereKey($foto->id)->exists());
        Storage::disk('public')->assertExists('avance/admin.jpg');
    }

    // ── SECURITY-UX-0.2 · 1-2: contraseña inicial definida por el admin ──

    public function test_alta_requiere_contrasena_inicial(): void
    {
        $datos = array_diff_key($this->datosMecanico('sin-clave@taller.test', 'mechanic'), ['password' => 1, 'password_confirmation' => 1]);

        $this->actingAs($this->admin())->post(route('mechanics.store'), $datos)->assertSessionHasErrors('password');
        $this->assertFalse(User::where('email', 'sin-clave@taller.test')->exists());
    }

    public function test_alta_requiere_confirmacion_de_contrasena(): void
    {
        $datos = $this->datosMecanico('confirma@taller.test', 'mechanic');
        $datos['password_confirmation'] = 'Otra-Clave-2026';

        $this->actingAs($this->admin())->post(route('mechanics.store'), $datos)->assertSessionHasErrors('password');
        $this->assertFalse(User::where('email', 'confirma@taller.test')->exists());
    }

    public function test_alta_rechaza_contrasena_debil(): void
    {
        $this->actingAs($this->admin())
            ->post(route('mechanics.store'), $this->datosMecanico('debil@taller.test', 'mechanic', 'abc'))
            ->assertSessionHasErrors('password');

        $this->assertFalse(User::where('email', 'debil@taller.test')->exists());
    }

    public function test_alta_con_contrasena_valida_guarda_solo_el_hash(): void
    {
        $this->actingAs($this->admin())
            ->post(route('mechanics.store'), $this->datosMecanico('hash@taller.test', 'mechanic', 'Clave-Inicial-2026'))
            ->assertRedirect(route('mechanics.index'));

        $user = User::where('email', 'hash@taller.test')->firstOrFail();
        $this->assertTrue(Hash::isHashed($user->password));
        $this->assertTrue(Hash::check('Clave-Inicial-2026', $user->password));
        $this->assertNotSame('Clave-Inicial-2026', $user->password);
        $this->assertFalse(Hash::check('password123', $user->password));
    }

    public function test_contrasena_no_aparece_en_flash_ni_en_sesion(): void
    {
        $this->actingAs($this->admin())
            ->post(route('mechanics.store'), $this->datosMecanico('flash@taller.test', 'mechanic', 'Clave-Inicial-2026'))
            ->assertSessionHas('success');

        $this->assertStringNotContainsString('Clave-Inicial-2026', json_encode(session()->all()));
    }

    public function test_error_de_validacion_no_conserva_la_contrasena_en_sesion(): void
    {
        $datos = $this->datosMecanico('viejo@taller.test', 'mechanic');
        $datos['password_confirmation'] = 'Distinta-Clave-2026';

        $this->actingAs($this->admin())->post(route('mechanics.store'), $datos)->assertSessionHasErrors('password');
        $this->assertStringNotContainsString('Clave-Inicial-2026', json_encode(session()->all()));
    }

    // ── Edición: la contraseña existente no se toca ──────────────────────

    public function test_editar_mecanico_con_cuenta_conserva_la_contrasena(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('mechanics.store'), $this->datosMecanico('edita@taller.test', 'mechanic', 'Clave-Inicial-2026'));
        $user = User::where('email', 'edita@taller.test')->firstOrFail();
        $mecanico = Mechanic::where('user_id', $user->id)->firstOrFail();
        $hashAntes = $user->password;

        $datos = $this->datosMecanico('edita@taller.test', 'mechanic');
        $datos['name'] = 'Nombre Editado';
        $datos['password'] = '';
        $datos['password_confirmation'] = '';

        $this->actingAs($admin)->put(route('mechanics.update', $mecanico), $datos)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('mechanics.index'));

        $user->refresh();
        $this->assertSame($hashAntes, $user->password);
        $this->assertTrue(Hash::check('Clave-Inicial-2026', $user->password));
    }

    public function test_no_se_cambia_la_contrasena_de_una_cuenta_existente_desde_edicion(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('mechanics.store'), $this->datosMecanico('cambio@taller.test', 'mechanic', 'Clave-Inicial-2026'));
        $user = User::where('email', 'cambio@taller.test')->firstOrFail();
        $mecanico = Mechanic::where('user_id', $user->id)->firstOrFail();

        $datos = $this->datosMecanico('cambio@taller.test', 'mechanic', 'Otra-Clave-2026');

        $this->actingAs($admin)->put(route('mechanics.update', $mecanico), $datos)
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Clave-Inicial-2026', $user->fresh()->password));
    }

    public function test_mecanico_sin_cuenta_no_crea_cuenta_si_no_se_indica_contrasena(): void
    {
        $mecanico = Mechanic::create([
            'name'             => 'Sin cuenta',
            'email'            => 'sincuenta@taller.test',
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        $this->actingAs($this->admin())
            ->put(route('mechanics.update', $mecanico), $this->datosMecanico('sincuenta@taller.test', 'mechanic', ''))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'Aún no tiene cuenta'));

        $this->assertNull($mecanico->fresh()->user_id);
        $this->assertFalse(User::where('email', 'sincuenta@taller.test')->exists());
    }

    public function test_mecanico_sin_cuenta_crea_cuenta_con_contrasena_inicial(): void
    {
        $mecanico = Mechanic::create([
            'name'             => 'Con clave',
            'email'            => 'conclave@taller.test',
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        $this->actingAs($this->admin())
            ->put(route('mechanics.update', $mecanico), $this->datosMecanico('conclave@taller.test', 'mechanic', 'Clave-Legado-2026'))
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'conclave@taller.test')->firstOrFail();
        $this->assertTrue(Hash::check('Clave-Legado-2026', $user->password));
        $this->assertSame($user->id, (int) $mecanico->fresh()->user_id);
    }

    // ── Privacidad: lista de personal solo para admin/owner ───────────────

    public function test_admin_recibe_la_lista_de_mecanicos_en_la_orden(): void
    {
        [, $uno] = $this->mechanic('Carlos');
        $this->mechanic('Miguel');
        $orden = $this->orderIn('en-reparacion', $uno);

        $this->actingAs($this->admin())
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('mechanics', 2));
    }

    public function test_mecanico_no_recibe_la_lista_de_personal(): void
    {
        [$user, $propio] = $this->mechanic('Carlos');
        $this->mechanic('Miguel');
        $orden = $this->orderIn('en-reparacion', $propio);

        $this->actingAs($user)
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('mechanics', []));
    }

    // ── Bitácora: borrado solo de notas propias en órdenes abiertas ───────

    public function test_mecanico_elimina_su_propia_nota_en_orden_abierta(): void
    {
        [$user, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('en-reparacion', $mecanico);
        $nota = FollowUp::create(['repair_order_id' => $orden->id, 'mechanic_id' => $mecanico->id, 'description' => 'Mía', 'date' => '2026-11-22']);

        $this->actingAs($user)->delete(route('repair-orders.follow-ups.destroy', [$orden, $nota]))->assertRedirect();

        $this->assertFalse(FollowUp::whereKey($nota->id)->exists());
    }

    public function test_mecanico_no_elimina_nota_de_otro_mecanico(): void
    {
        [$user, $mecanico] = $this->mechanic('Carlos');
        [, $otro] = $this->mechanic('Miguel');
        $orden = $this->orderIn('en-reparacion', $mecanico);
        $nota = FollowUp::create(['repair_order_id' => $orden->id, 'mechanic_id' => $otro->id, 'description' => 'Ajena', 'date' => '2026-11-22']);

        $this->actingAs($user)->delete(route('repair-orders.follow-ups.destroy', [$orden, $nota]))->assertForbidden();

        $this->assertTrue(FollowUp::whereKey($nota->id)->exists());
    }

    public function test_mecanico_no_elimina_su_nota_en_orden_cerrada(): void
    {
        [$user, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('entregado', $mecanico);
        $nota = FollowUp::create(['repair_order_id' => $orden->id, 'mechanic_id' => $mecanico->id, 'description' => 'Cerrada', 'date' => '2026-11-22']);

        $this->actingAs($user)->delete(route('repair-orders.follow-ups.destroy', [$orden, $nota]))->assertForbidden();

        $this->assertTrue(FollowUp::whereKey($nota->id)->exists());
    }

    public function test_admin_elimina_nota_de_cualquier_mecanico(): void
    {
        [, $mecanico] = $this->mechanic();
        [, $otro] = $this->mechanic('Otro');
        $orden = $this->orderIn('en-reparacion', $mecanico);
        $nota = FollowUp::create(['repair_order_id' => $orden->id, 'mechanic_id' => $otro->id, 'description' => 'Ajena', 'date' => '2026-11-22']);

        $this->actingAs($this->admin())->delete(route('repair-orders.follow-ups.destroy', [$orden, $nota]))->assertRedirect();

        $this->assertFalse(FollowUp::whereKey($nota->id)->exists());
    }

    public function test_no_se_elimina_una_nota_a_traves_de_otra_orden(): void
    {
        [$user, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('en-reparacion', $mecanico);
        $otraOrden = $this->orderIn('en-reparacion', $mecanico);
        $nota = FollowUp::create(['repair_order_id' => $otraOrden->id, 'mechanic_id' => $mecanico->id, 'description' => 'Otra', 'date' => '2026-11-22']);

        $this->actingAs($user)->delete(route('repair-orders.follow-ups.destroy', [$orden, $nota]))->assertNotFound();

        $this->assertTrue(FollowUp::whereKey($nota->id)->exists());
    }

    public function test_la_vista_marca_puede_eliminar_solo_en_notas_propias(): void
    {
        [$user, $mecanico] = $this->mechanic('Carlos');
        [, $otro] = $this->mechanic('Miguel');
        $orden = $this->orderIn('en-reparacion', $mecanico);
        FollowUp::create(['repair_order_id' => $orden->id, 'mechanic_id' => $otro->id, 'description' => 'Ajena', 'date' => '2026-11-01']);
        FollowUp::create(['repair_order_id' => $orden->id, 'mechanic_id' => $mecanico->id, 'description' => 'Propia', 'date' => '2026-11-22']);

        $this->actingAs($user)
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('orden.follow_ups', 2)
                ->where('orden.follow_ups.0.puede_eliminar', true)
                ->where('orden.follow_ups.1.puede_eliminar', false));
    }

    public function test_la_vista_indica_orden_cerrada(): void
    {
        [$user, $mecanico] = $this->mechanic();
        $orden = $this->orderIn('entregado', $mecanico);

        $this->actingAs($user)
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('orden.esta_cerrada', true));
    }
}
