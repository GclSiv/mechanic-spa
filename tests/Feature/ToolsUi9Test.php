<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Tool;
use App\Models\ToolMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * UI-9: herramientas con evidencia fotográfica/video. Disco privado falso; sin archivos reales.
 */
class ToolsUi9Test extends TestCase
{
    use RefreshDatabase;

    // Cabecera mínima reconocible por libmagic como MP4 (ftyp) y WebM (EBML + DocType).
    private const MP4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free";
    private const WEBM = "\x1aE\xdf\xa3\x01\x00\x00\x00\x00\x00\x00\x1f\x42\x86\x81\x01\x42\xf7\x81\x01\x42\xf2\x81\x04\x42\xf3\x81\x08\x42\x82\x84webm\x42\x87\x81\x02\x42\x85\x81\x02";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ToolMedia::DISCO);
    }

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

    private function datos(array $extra = []): array
    {
        return array_merge([
            'category_id'   => Category::where('type', Category::TYPE_TOOL)->value('id'),
            'location_id'   => Location::where('name', 'Almacén')->value('id'),
            'name'          => 'Multímetro digital',
            'brand'         => 'Fluke',
            'model'         => '117',
            'serial_number' => 'SN-9001',
            'internal_code' => 'HER-9001',
            'purchase_date' => '2026-01-15',
            'purchase_cost' => 2500.50,
            'status'        => 'disponible',
            'condition'     => 'buena',
            'notes'         => 'Nota UI-9',
        ], $extra);
    }

    private function foto(string $nombre = 'foto.jpg', int $kb = 200): UploadedFile
    {
        return UploadedFile::fake()->image($nombre)->size($kb);
    }

    private function video(string $nombre = 'clip.mp4'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, self::MP4);
    }

    private function herramientaConEvidencia(User $admin): Tool
    {
        $this->actingAs($admin)->post(route('tools.store'), $this->datos(['evidencia' => $this->foto()]))
            ->assertRedirect(route('tools.index'));

        return Tool::where('internal_code', 'HER-9001')->firstOrFail();
    }

    public function test_mecanico_no_accede_a_herramientas_ni_a_su_evidencia(): void
    {
        $admin = $this->admin();
        $herramienta = $this->herramientaConEvidencia($admin);
        $medio = $herramienta->media()->firstOrFail();
        $mecanico = $this->mecanico();

        $this->actingAs($mecanico)->get(route('tools.index'))->assertForbidden();
        $this->actingAs($mecanico)->get(route('tools.show', $herramienta))->assertForbidden();
        $this->actingAs($mecanico)->get(route('tools.media.show', [$herramienta, $medio]))->assertForbidden();
        $this->actingAs($mecanico)->post(route('tools.media.store', $herramienta), ['evidencia' => $this->foto()])->assertForbidden();
        $this->actingAs($mecanico)->delete(route('tools.media.destroy', [$herramienta, $medio]))->assertForbidden();
    }

    public function test_admin_y_owner_pueden_administrar_herramientas(): void
    {
        $this->actingAs($this->admin())->get(route('tools.index'))->assertOk();
        $this->actingAs($this->owner())->get(route('tools.index'))->assertOk();
    }

    public function test_el_listado_pagina_de_25_en_25(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            Tool::create($this->datos(['internal_code' => sprintf('HER-%04d', $i), 'serial_number' => "SN-{$i}"]));
        }

        $pagina = $this->actingAs($this->admin())->get(route('tools.index'))->assertOk()->inertiaProps('tools');

        $this->assertSame(25, $pagina['per_page']);
        $this->assertCount(25, $pagina['data']);
        $this->assertSame(26, $pagina['total']);
    }

    public function test_crear_sin_evidencia_sigue_funcionando(): void
    {
        $this->actingAs($this->admin())->post(route('tools.store'), $this->datos())
            ->assertRedirect(route('tools.index'));

        $this->assertDatabaseHas('tools', ['internal_code' => 'HER-9001']);
        $this->assertDatabaseCount('tool_media', 0);
    }

    public function test_crear_con_foto_guarda_en_disco_privado(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('tools.store'), $this->datos(['evidencia' => $this->foto()]))
            ->assertRedirect(route('tools.index'));

        $medio = ToolMedia::firstOrFail();
        $this->assertSame(ToolMedia::TIPO_IMAGEN, $medio->media_type);
        $this->assertSame('image/jpeg', $medio->mime_type);
        $this->assertSame($admin->id, $medio->uploaded_by);
        $this->assertStringStartsWith('tool-media/', $medio->path);
        Storage::disk(ToolMedia::DISCO)->assertExists($medio->path);
    }

    public function test_crear_con_video_mp4_y_webm(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('tools.store'), $this->datos(['evidencia' => $this->video()]))
            ->assertRedirect(route('tools.index'));

        $this->assertSame(ToolMedia::TIPO_VIDEO, ToolMedia::firstOrFail()->media_type);

        $webm = UploadedFile::fake()->createWithContent('clip.webm', self::WEBM);
        $this->actingAs($admin)->post(route('tools.media.store', Tool::firstOrFail()), ['evidencia' => $webm])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('tool_media', 2);
    }

    public function test_rechaza_formatos_no_permitidos(): void
    {
        $admin = $this->admin();
        $herramienta = $this->herramientaConEvidencia($admin);

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $texto = UploadedFile::fake()->createWithContent('nota.txt', 'hola');

        $this->actingAs($admin)->post(route('tools.media.store', $herramienta), ['evidencia' => $svg])
            ->assertSessionHasErrors('evidencia');
        $this->actingAs($admin)->post(route('tools.media.store', $herramienta), ['evidencia' => $texto])
            ->assertSessionHasErrors('evidencia');

        $this->assertDatabaseCount('tool_media', 1);
    }

    public function test_rechaza_foto_mayor_al_limite_de_imagen(): void
    {
        $admin = $this->admin();
        $herramienta = $this->herramientaConEvidencia($admin);

        $this->actingAs($admin)->post(route('tools.media.store', $herramienta), [
            'evidencia' => $this->foto('grande.jpg', ToolMedia::MAX_IMAGEN_KB + 50),
        ])->assertSessionHasErrors('evidencia');

        $this->assertDatabaseCount('tool_media', 1);
    }

    public function test_al_agregar_evidencia_sin_archivo_se_rechaza(): void
    {
        $admin = $this->admin();
        $herramienta = $this->herramientaConEvidencia($admin);

        $this->actingAs($admin)->post(route('tools.media.store', $herramienta), [])
            ->assertSessionHasErrors('evidencia');
    }

    public function test_sirve_la_evidencia_solo_por_la_ruta_autorizada(): void
    {
        $admin = $this->admin();
        $herramienta = $this->herramientaConEvidencia($admin);
        $medio = $herramienta->media()->firstOrFail();

        $this->actingAs($admin)->get(route('tools.media.show', [$herramienta, $medio]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $otra = Tool::create($this->datos(['internal_code' => 'HER-9002', 'serial_number' => 'SN-9002']));
        $this->actingAs($admin)->get(route('tools.media.show', [$otra, $medio]))->assertNotFound();
    }

    public function test_la_vista_no_expone_rutas_internas(): void
    {
        $admin = $this->admin();
        $herramienta = $this->herramientaConEvidencia($admin);

        $props = $this->actingAs($admin)->get(route('tools.show', $herramienta))->assertOk()->inertiaProps();
        $json = json_encode($props, JSON_UNESCAPED_SLASHES);

        $this->assertStringNotContainsString('tool-media/', $json);
        $this->assertStringNotContainsString('"path"', $json);
        $this->assertSame(
            route('tools.media.show', [$herramienta, $herramienta->media()->first()]),
            $props['evidencia'][0]['url'],
        );
    }

    public function test_eliminar_evidencia_borra_archivo_y_registro(): void
    {
        $admin = $this->admin();
        $herramienta = $this->herramientaConEvidencia($admin);
        $medio = $herramienta->media()->firstOrFail();
        $ruta = $medio->path;

        $this->actingAs($admin)->delete(route('tools.media.destroy', [$herramienta, $medio]))
            ->assertRedirect();

        $this->assertDatabaseMissing('tool_media', ['id' => $medio->id]);
        Storage::disk(ToolMedia::DISCO)->assertMissing($ruta);
    }

    public function test_editar_conserva_la_evidencia_y_permite_agregar_mas(): void
    {
        $admin = $this->admin();
        $herramienta = $this->herramientaConEvidencia($admin);

        $this->actingAs($admin)->put(route('tools.update', $herramienta), $this->datos([
            'name' => 'Nombre editado', 'status' => 'en_uso',
        ]))->assertRedirect(route('tools.index'));

        $this->assertSame(1, $herramienta->media()->count());

        $this->actingAs($admin)->put(route('tools.update', $herramienta), $this->datos([
            'name' => 'Nombre editado', 'evidencia' => $this->video(),
        ]))->assertRedirect(route('tools.index'));

        $this->assertSame(2, $herramienta->fresh()->media()->count());
    }

    public function test_dar_de_baja_conserva_la_evidencia(): void
    {
        $admin = $this->admin();
        $herramienta = $this->herramientaConEvidencia($admin);

        $this->actingAs($admin)->delete(route('tools.destroy', $herramienta))->assertRedirect(route('tools.index'));

        $herramienta->refresh();
        $this->assertSame('baja', $herramienta->status);
        $this->assertFalse($herramienta->active);
        $this->assertSame(1, $herramienta->media()->count());
        Storage::disk(ToolMedia::DISCO)->assertExists($herramienta->media()->firstOrFail()->path);
    }

    public function test_el_catalogo_de_categorias_sigue_siendo_solo_herramientas(): void
    {
        $categorias = $this->actingAs($this->admin())->get(route('tools.create'))
            ->assertOk()->inertiaProps('categories');

        foreach ($categorias as $categoria) {
            $this->assertSame(Category::TYPE_TOOL, Category::find($categoria['id'])->type);
        }
    }

    public function test_owner_puede_abrir_foto_y_video(): void
    {
        $admin = $this->admin();
        $herramienta = $this->herramientaConEvidencia($admin);
        $this->actingAs($admin)->post(route('tools.media.store', $herramienta), ['evidencia' => $this->video()]);

        foreach ($herramienta->media()->get() as $medio) {
            $this->actingAs($this->owner())->get(route('tools.media.show', [$herramienta, $medio]))->assertOk();
        }
    }

    public function test_sin_sesion_no_se_puede_abrir_la_evidencia(): void
    {
        // Se crea sin actingAs para que la petición no herede ninguna sesión.
        $herramienta = Tool::create($this->datos());
        $medio = ToolMedia::guardar($herramienta, $this->foto(), null);

        $this->get(route('tools.media.show', [$herramienta, $medio]))->assertRedirect();
    }

    public function test_la_evidencia_se_sirve_inline_y_no_fuerza_descarga(): void
    {
        $admin = $this->admin();
        $herramienta = $this->herramientaConEvidencia($admin);
        $medio = $herramienta->media()->firstOrFail();

        $respuesta = $this->actingAs($admin)->get(route('tools.media.show', [$herramienta, $medio]))->assertOk();

        $this->assertStringStartsWith('inline', (string) $respuesta->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $respuesta->headers->get('X-Content-Type-Options'));
    }

    public function test_el_video_soporta_peticiones_parciales_para_navegar(): void
    {
        $admin = $this->admin();
        $herramienta = $this->herramientaConEvidencia($admin);
        $this->actingAs($admin)->post(route('tools.media.store', $herramienta), ['evidencia' => $this->video()]);
        $video = $herramienta->media()->where('media_type', ToolMedia::TIPO_VIDEO)->firstOrFail();

        $respuesta = $this->actingAs($admin)
            ->withHeaders(['Range' => 'bytes=0-7'])
            ->get(route('tools.media.show', [$herramienta, $video]));

        $respuesta->assertStatus(206);
        $this->assertStringStartsWith('bytes 0-7/', (string) $respuesta->headers->get('Content-Range'));
    }

    public function test_el_archivo_guardado_usa_nombre_generado_y_disco_privado(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('tools.store'), $this->datos([
            'evidencia' => UploadedFile::fake()->image('../../etc/passwd.jpg')->size(50),
        ]));

        $medio = ToolMedia::firstOrFail();
        $this->assertStringStartsWith('tool-media/', $medio->path);
        $this->assertStringNotContainsString('..', $medio->path);
        $this->assertStringNotContainsString('passwd', $medio->path);
        $this->assertSame('passwd.jpg', $medio->original_name);
        Storage::disk(ToolMedia::DISCO)->assertExists($medio->path);
    }
}
