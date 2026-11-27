<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPhoto;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\ArchivoPrivado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * RC1-001: fotos privadas (cliente, recepción, notas, avance y seguimiento).
 * Ningún archivo privado es accesible por URL pública directa; todo pasa por endpoints autorizados.
 */
class MediaPrivadaRc1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
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

    /** Orden con recepción (con fotos privadas) y mecánico opcional. */
    private function orden(?Mechanic $mecanico = null, array $fotos = []): RepairOrder
    {
        $status = RepairOrderStatus::query()->firstOrCreate(
            ['slug' => 'diagnostico'],
            ['name' => 'diagnostico', 'color_class' => 'bg-gray-500']
        );
        $cliente = Client::factory()->create();
        $vehiculo = Vehicle::factory()->create(['client_id' => $cliente->id]);
        $recepcion = Recepcion::create([
            'client_id' => $cliente->id, 'vehicle_id' => $vehiculo->id, 'fuel_level' => '1/2',
            'photos' => $fotos ?: null,
        ]);

        return RepairOrder::factory()->create([
            'recepcion_id' => $recepcion->id, 'tax_rate' => 16, 'status_id' => $status->id,
            'mechanic_id' => $mecanico?->id,
        ]);
    }

    /** Guarda una imagen real en el disco privado y devuelve su ruta relativa. */
    private function imagenPrivada(string $ruta): string
    {
        Storage::disk('local')->put($ruta, UploadedFile::fake()->image('foto.jpg')->getContent());

        return $ruta;
    }

    // ── 1–3. Acceso por rol ───────────────────────────────────────────────

    public function test_invitado_no_accede_a_fotos_de_recepcion_ni_de_cliente(): void
    {
        $orden = $this->orden(null, [$this->imagenPrivada('recepciones/a.jpg')]);
        $foto = ClientPhoto::create(['client_id' => $orden->client_id, 'path' => $this->imagenPrivada('clientes/1/b.jpg')]);

        $this->get(route('recepcion.foto', [$orden->recepcion_id, 0]))->assertRedirectContains('login');
        $this->get(route('clients.photos.show', [$orden->client_id, $foto->id]))->assertRedirectContains('login');
    }

    public function test_mecanico_sin_orden_asignada_no_accede_a_fotos_de_recepcion(): void
    {
        $orden = $this->orden(null, [$this->imagenPrivada('recepciones/a.jpg')]);
        $mecanico = $this->usuario(User::ROLE_MECHANIC);
        $this->mecanico($mecanico);

        $this->actingAs($mecanico)->get(route('recepcion.foto', [$orden->recepcion_id, 0]))->assertForbidden();
    }

    public function test_mecanico_no_accede_a_fotos_de_cliente_aunque_tenga_orden(): void
    {
        $usuario = $this->usuario(User::ROLE_MECHANIC);
        $orden = $this->orden($this->mecanico($usuario));
        $foto = ClientPhoto::create(['client_id' => $orden->client_id, 'path' => $this->imagenPrivada('clientes/1/b.jpg')]);

        $this->actingAs($usuario)->get(route('clients.photos.show', [$orden->client_id, $foto->id]))->assertForbidden();
    }

    public function test_admin_y_owner_acceden_a_fotos_privadas(): void
    {
        $orden = $this->orden(null, [$this->imagenPrivada('recepciones/a.jpg')]);
        $foto = ClientPhoto::create(['client_id' => $orden->client_id, 'path' => $this->imagenPrivada('clientes/1/b.jpg')]);

        foreach ([User::ROLE_ADMIN, User::ROLE_OWNER] as $rol) {
            $usuario = $this->usuario($rol);
            $this->actingAs($usuario)->get(route('recepcion.foto', [$orden->recepcion_id, 0]))->assertOk();
            $this->actingAs($usuario)->get(route('clients.photos.show', [$orden->client_id, $foto->id]))->assertOk();
        }
    }

    // ── 4. Mecánico asignado: solo lo permitido ───────────────────────────

    public function test_mecanico_asignado_ve_la_foto_de_recepcion_de_su_orden_y_no_la_ajena(): void
    {
        $usuario = $this->usuario(User::ROLE_MECHANIC);
        $propia = $this->orden($this->mecanico($usuario), [$this->imagenPrivada('recepciones/propia.jpg')]);
        $ajena = $this->orden(null, [$this->imagenPrivada('recepciones/ajena.jpg')]);

        $this->actingAs($usuario)->get(route('recepcion.foto', [$propia->recepcion_id, 0]))->assertOk();
        $this->actingAs($usuario)->get(route('recepcion.foto', [$ajena->recepcion_id, 0]))->assertForbidden();
    }

    // ── 5. IDOR ───────────────────────────────────────────────────────────

    public function test_idor_nota_de_otra_orden_no_se_sirve_por_la_orden_propia(): void
    {
        $usuario = $this->usuario(User::ROLE_MECHANIC);
        $mec = $this->mecanico($usuario);
        $propia = $this->orden($mec);
        $ajena = $this->orden($mec);
        $nota = $ajena->followUps()->create([
            'mechanic_id' => $mec->id, 'description' => 'Nota', 'date' => now()->toDateString(),
            'evidence_photos' => [$this->imagenPrivada('notas/' . $ajena->id . '/n.jpg')],
        ]);

        $this->actingAs($usuario)
            ->get(route('repair-orders.follow-ups.foto', [$propia->id, $nota->id, 0]))
            ->assertNotFound();
        $this->actingAs($usuario)
            ->get(route('repair-orders.follow-ups.foto', [$ajena->id, $nota->id, 0]))
            ->assertOk();
    }

    public function test_idor_foto_de_otro_cliente_no_se_sirve_con_el_cliente_propio(): void
    {
        $admin = $this->usuario(User::ROLE_ADMIN);
        $clienteA = Client::factory()->create();
        $clienteB = Client::factory()->create();
        $foto = ClientPhoto::create(['client_id' => $clienteB->id, 'path' => $this->imagenPrivada('clientes/b.jpg')]);

        $this->actingAs($admin)->get(route('clients.photos.show', [$clienteA->id, $foto->id]))->assertNotFound();
        $this->actingAs($admin)->get(route('clients.photos.show', [$clienteB->id, $foto->id]))->assertOk();
    }

    public function test_indice_fuera_de_rango_responde_404(): void
    {
        $orden = $this->orden(null, [$this->imagenPrivada('recepciones/a.jpg')]);

        $this->actingAs($this->usuario(User::ROLE_ADMIN))
            ->get(route('recepcion.foto', [$orden->recepcion_id, 5]))
            ->assertNotFound();
    }

    // ── 6–7. Tracking público ─────────────────────────────────────────────

    public function test_tracking_valido_accede_solo_a_la_evidencia_de_su_orden(): void
    {
        $orden = $this->orden();
        $otra = $this->orden();
        $foto = $orden->avancePhotos()->create(['path' => $this->imagenPrivada('avance/' . $orden->id . '/p.jpg')]);
        $fotoAjena = $otra->avancePhotos()->create(['path' => $this->imagenPrivada('avance/' . $otra->id . '/q.jpg')]);

        $this->get(route('tracking.avance.photo', ['token' => $orden->tracking_token, 'photo' => $foto->id]))->assertOk();
        $this->get(route('tracking.avance.photo', ['token' => $orden->tracking_token, 'photo' => $fotoAjena->id]))->assertNotFound();
    }

    public function test_tracking_invalido_o_inexistente_falla(): void
    {
        $orden = $this->orden();
        $foto = $orden->avancePhotos()->create(['path' => $this->imagenPrivada('avance/' . $orden->id . '/p.jpg')]);

        $this->get(route('tracking.avance.photo', ['token' => str_repeat('a', 48), 'photo' => $foto->id]))->assertNotFound();
        $this->get(route('tracking.avance.photo', ['token' => $orden->tracking_token, 'photo' => 999999]))->assertNotFound();
    }

    // ── 8. Archivo inexistente ────────────────────────────────────────────

    public function test_archivo_inexistente_responde_404(): void
    {
        $orden = $this->orden(null, ['recepciones/no-existe.jpg']);

        $this->actingAs($this->usuario(User::ROLE_ADMIN))
            ->get(route('recepcion.foto', [$orden->recepcion_id, 0]))
            ->assertNotFound();
    }

    // ── 9. Path traversal ─────────────────────────────────────────────────

    public function test_path_traversal_es_imposible(): void
    {
        $this->assertNull(ArchivoPrivado::rutaSegura('../../.env'));
        $this->assertNull(ArchivoPrivado::rutaSegura('/etc/passwd'));
        $this->assertNull(ArchivoPrivado::rutaSegura('recepciones\\..\\..\\x.jpg'));
        $this->assertNull(ArchivoPrivado::rutaSegura("recepciones/x.jpg\0"));
        $this->assertSame('recepciones/2026/foto.jpg', ArchivoPrivado::rutaSegura('recepciones/2026/foto.jpg'));

        $orden = $this->orden(null, ['../../.env']);
        $this->actingAs($this->usuario(User::ROLE_ADMIN))
            ->get(route('recepcion.foto', [$orden->recepcion_id, 0]))
            ->assertNotFound();
    }

    // ── 10. Archivos nuevos no generan URL pública ────────────────────────

    public function test_archivos_privados_nuevos_no_se_guardan_en_el_disco_publico_ni_generan_url_publica(): void
    {
        $admin = $this->usuario(User::ROLE_ADMIN);
        $cliente = Client::factory()->create();

        $this->actingAs($admin)->post(route('clients.photos.store', $cliente), [
            'photos' => [UploadedFile::fake()->image('golpe.jpg')],
        ])->assertRedirect();

        $foto = ClientPhoto::where('client_id', $cliente->id)->firstOrFail();

        $this->assertNotEmpty(Storage::disk('local')->allFiles());
        $this->assertEmpty(Storage::disk('public')->allFiles());
        $this->assertStringNotContainsString('/storage/', (string) $foto->url);
        $this->assertStringContainsString('/clients/' . $cliente->id . '/photos/' . $foto->id, (string) $foto->url);
        $this->assertArrayNotHasKey('path', $foto->toArray());
    }

    public function test_respuesta_de_foto_es_imagen_inline_privada_y_sin_ejecucion(): void
    {
        $orden = $this->orden(null, [$this->imagenPrivada('recepciones/a.jpg')]);

        $respuesta = $this->actingAs($this->usuario(User::ROLE_ADMIN))
            ->get(route('recepcion.foto', [$orden->recepcion_id, 0]))
            ->assertOk();

        $this->assertStringStartsWith('image/', $respuesta->headers->get('content-type'));
        $this->assertSame('nosniff', $respuesta->headers->get('x-content-type-options'));
        $this->assertStringStartsWith('inline', (string) $respuesta->headers->get('content-disposition'));
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('cache-control'));
    }

    // ── Compatibilidad legada y migración ─────────────────────────────────

    public function test_legado_publico_se_sirve_por_endpoint_a_admin_autorizado(): void
    {
        Storage::disk('public')->put('recepciones/legado.jpg', UploadedFile::fake()->image('l.jpg')->getContent());
        $orden = $this->orden(null, ['recepciones/legado.jpg']);

        $this->actingAs($this->usuario(User::ROLE_ADMIN))
            ->get(route('recepcion.foto', [$orden->recepcion_id, 0]))
            ->assertOk();
    }

    public function test_legado_publico_no_se_sirve_a_invitado(): void
    {
        Storage::disk('public')->put('recepciones/legado.jpg', UploadedFile::fake()->image('l.jpg')->getContent());
        $orden = $this->orden(null, ['recepciones/legado.jpg']);

        $this->get(route('recepcion.foto', [$orden->recepcion_id, 0]))->assertRedirectContains('login');
    }

    public function test_comando_migra_sin_duplicar_y_retira_solo_con_bandera(): void
    {
        Storage::disk('public')->put('recepciones/mig.jpg', UploadedFile::fake()->image('m.jpg')->getContent());
        $orden = $this->orden(null, ['recepciones/mig.jpg', 'recepciones/faltante.jpg']);

        $this->artisan('app:migrate-private-media')->assertExitCode(0);
        $this->assertTrue(Storage::disk('local')->exists('recepciones/mig.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('recepciones/mig.jpg'), 'Sin --retirar el origen se conserva.');
        $this->assertSame(
            Storage::disk('public')->get('recepciones/mig.jpg'),
            Storage::disk('local')->get('recepciones/mig.jpg')
        );

        // Reejecución: no duplica ni falla.
        $this->artisan('app:migrate-private-media')->assertExitCode(0);

        // Con --retirar, el origen solo se borra después de verificar la copia.
        $this->artisan('app:migrate-private-media', ['--retirar' => true])->assertExitCode(0);
        $this->assertFalse(Storage::disk('public')->exists('recepciones/mig.jpg'));
        $this->assertTrue(Storage::disk('local')->exists('recepciones/mig.jpg'));

        // La recepción sigue sirviéndose desde el disco privado.
        $this->actingAs($this->usuario(User::ROLE_ADMIN))
            ->get(route('recepcion.foto', [$orden->recepcion_id, 0]))
            ->assertOk();
    }

    public function test_comando_no_sobrescribe_una_copia_privada_distinta(): void
    {
        Storage::disk('public')->put('recepciones/choque.jpg', 'original publico');
        Storage::disk('local')->put('recepciones/choque.jpg', 'otra copia privada');
        $this->orden(null, ['recepciones/choque.jpg']);

        $this->artisan('app:migrate-private-media')->assertExitCode(1);
        $this->assertSame('otra copia privada', Storage::disk('local')->get('recepciones/choque.jpg'));
    }
}
