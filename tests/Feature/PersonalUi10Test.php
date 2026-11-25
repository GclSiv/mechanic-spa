<?php

namespace Tests\Feature;

use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * UI-10 · Personal: catálogos de género y especialidad (idempotentes), permisos y alta/edición.
 */
class PersonalUi10Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function mecanico(): User
    {
        return User::factory()->create(['role' => User::ROLE_MECHANIC]);
    }

    private function tipo(string $nombre): MechanicType
    {
        return MechanicType::where('name', $nombre)->firstOrFail();
    }

    private function genero(string $nombre): Gender
    {
        return Gender::where('name', $nombre)->firstOrFail();
    }

    // ── Catálogos ─────────────────────────────────────────────────────────

    public function test_genero_catalogo_tiene_masculino_femenino_y_no_especificar(): void
    {
        $nombres = Gender::query()->pluck('name')->all();

        $this->assertContains('Masculino', $nombres);
        $this->assertContains('Femenino', $nombres);
        $this->assertContains('No especificar', $nombres);
        $this->assertNotContains('Male', $nombres);
        $this->assertNotContains('Female', $nombres);
    }

    public function test_sembrar_catalogos_dos_veces_no_duplica(): void
    {
        $generos = Gender::count();
        $tipos = MechanicType::count();

        $this->seed(CatalogSeeder::class);

        $this->assertSame($generos, Gender::count());
        $this->assertSame($tipos, MechanicType::count());
        $this->assertSame(1, Gender::where('name', 'Masculino')->count());
        $this->assertSame(1, MechanicType::where('name', 'Motocicletas')->count());
    }

    public function test_especialidades_nuevas_existen_en_el_catalogo(): void
    {
        $this->assertTrue(MechanicType::where('name', 'Motocicletas')->exists());
        $this->assertTrue(MechanicType::where('name', 'Mecánico Gasolina')->exists());
    }

    // ── Permisos ──────────────────────────────────────────────────────────

    public function test_mecanico_no_puede_ver_ni_crear_ni_editar_personal(): void
    {
        $user = $this->mecanico();
        $mec = Mechanic::create([
            'name' => 'Otro', 'gender_id' => $this->genero('Masculino')->id,
            'mechanic_type_id' => $this->tipo('Motocicletas')->id,
        ]);

        $this->actingAs($user)->get(route('mechanics.index'))->assertForbidden();
        $this->actingAs($user)->get(route('mechanics.create'))->assertForbidden();
        $this->actingAs($user)->get(route('mechanics.edit', $mec))->assertForbidden();
        $this->actingAs($user)->post(route('mechanics.store'), [])->assertForbidden();
    }

    public function test_admin_ve_personal_sin_exponer_password_ni_hash(): void
    {
        $admin = $this->admin();
        $cuenta = User::factory()->create(['role' => User::ROLE_MECHANIC]);
        Mechanic::create([
            'name' => 'Con cuenta', 'user_id' => $cuenta->id,
            'gender_id' => $this->genero('Femenino')->id,
            'mechanic_type_id' => $this->tipo('Mecánico Gasolina')->id,
        ]);

        $this->actingAs($admin)
            ->get(route('mechanics.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Mechanics/Index')
                ->where('mechanics.0.mechanic_type.name', 'Mecánico Gasolina')
                ->where('mechanics.0.gender.name', 'Femenino')
                ->missing('mechanics.0.user.password')
                ->missing('mechanics.0.user.remember_token')
            );
    }

    // ── Alta y edición ────────────────────────────────────────────────────

    public function test_admin_crea_personal_con_especialidad_y_genero(): void
    {
        $this->actingAs($this->admin())->post(route('mechanics.store'), [
            'name' => 'Nuevo Mecánico',
            'email' => 'nuevo@taller.test',
            'role' => 'mechanic',
            'gender_id' => $this->genero('No especificar')->id,
            'mechanic_type_id' => $this->tipo('Motocicletas')->id,
            'password' => 'Secreto-2026!',
            'password_confirmation' => 'Secreto-2026!',
        ])->assertRedirect(route('mechanics.index'));

        $this->assertDatabaseHas('mechanics', [
            'email' => 'nuevo@taller.test',
            'mechanic_type_id' => $this->tipo('Motocicletas')->id,
            'gender_id' => $this->genero('No especificar')->id,
        ]);
    }

    public function test_alta_rechaza_genero_inexistente(): void
    {
        $this->actingAs($this->admin())->post(route('mechanics.store'), [
            'name' => 'X', 'email' => 'x@taller.test', 'role' => 'mechanic',
            'gender_id' => 999999, 'mechanic_type_id' => $this->tipo('Motocicletas')->id,
            'password' => 'Secreto-2026!', 'password_confirmation' => 'Secreto-2026!',
        ])->assertSessionHasErrors('gender_id');
    }

    public function test_admin_cambia_especialidad_y_genero_al_editar(): void
    {
        $admin = $this->admin();
        $cuenta = User::factory()->create(['role' => User::ROLE_MECHANIC, 'email' => 'edit@taller.test']);
        $mec = Mechanic::create([
            'name' => 'Edit', 'email' => 'edit@taller.test', 'user_id' => $cuenta->id,
            'gender_id' => $this->genero('Masculino')->id,
            'mechanic_type_id' => $this->tipo('Motocicletas')->id,
        ]);

        $this->actingAs($admin)->put(route('mechanics.update', $mec), [
            'name' => 'Edit', 'email' => 'edit@taller.test', 'role' => 'mechanic',
            'gender_id' => $this->genero('Femenino')->id,
            'mechanic_type_id' => $this->tipo('Mecánico Gasolina')->id,
            'password' => '',
        ])->assertRedirect(route('mechanics.index'));

        $mec->refresh();
        $this->assertSame($this->tipo('Mecánico Gasolina')->id, $mec->mechanic_type_id);
        $this->assertSame($this->genero('Femenino')->id, $mec->gender_id);
    }

    public function test_editar_no_cambia_la_contrasena_de_una_cuenta_existente(): void
    {
        $cuenta = User::factory()->create(['role' => User::ROLE_MECHANIC, 'email' => 'pw@taller.test']);
        $hashAntes = $cuenta->password;
        $mec = Mechanic::create([
            'name' => 'Pw', 'email' => 'pw@taller.test', 'user_id' => $cuenta->id,
            'gender_id' => $this->genero('Masculino')->id,
            'mechanic_type_id' => $this->tipo('Motocicletas')->id,
        ]);

        $this->actingAs($this->admin())->put(route('mechanics.update', $mec), [
            'name' => 'Pw', 'email' => 'pw@taller.test', 'role' => 'mechanic',
            'gender_id' => $this->genero('Masculino')->id,
            'mechanic_type_id' => $this->tipo('Motocicletas')->id,
            'password' => 'Otra-clave-2026!', 'password_confirmation' => 'Otra-clave-2026!',
        ])->assertSessionHasErrors('password');

        $this->assertSame($hashAntes, $cuenta->fresh()->password);
    }

    public function test_editar_sin_commission_rate_conserva_el_porcentaje_historico(): void
    {
        $cuenta = User::factory()->create(['role' => User::ROLE_MECHANIC, 'email' => 'rate@taller.test']);
        $mec = Mechanic::create([
            'name' => 'Rate', 'email' => 'rate@taller.test', 'user_id' => $cuenta->id,
            'gender_id' => $this->genero('Masculino')->id,
            'mechanic_type_id' => $this->tipo('Motocicletas')->id,
            'commission_rate' => 12.5,
        ]);

        $this->actingAs($this->admin())->put(route('mechanics.update', $mec), [
            'name' => 'Rate', 'email' => 'rate@taller.test', 'role' => 'mechanic',
            'gender_id' => $this->genero('Masculino')->id,
            'mechanic_type_id' => $this->tipo('Mecánico Gasolina')->id,
        ])->assertRedirect(route('mechanics.index'));

        $this->assertSame('12.50', (string) $mec->fresh()->commission_rate);
    }
}
