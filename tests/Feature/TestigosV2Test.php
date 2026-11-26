<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Client;
use App\Models\ClientStatus;
use App\Models\Recepcion;
use App\Models\Setting;
use App\Models\User;
use App\Models\VehicleModel;
use App\Models\VehicleType;
use App\Models\WarningLight;
use App\Support\IconoPersonalizado;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Database\Seeders\VehicleCatalogSeeder;
use Database\Seeders\WarningLightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testigos V2: catálogo warning_lights (FRENO y TPMS), validación contra catálogo, histórico y PDF.
 */
class TestigosV2Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function datosNueva(array $testigos = [], string $apellido = 'Testigo'): array
    {
        ClientStatus::firstOrCreate(['name' => 'Active'], ['color' => 'bg-green-500']);
        $marca = Brand::firstOrCreate(['name' => 'Genérica']);
        $modelo = VehicleModel::firstOrCreate(['brand_id' => $marca->id, 'name' => 'Modelo']);

        return [
            'first_name' => 'Cliente',
            'last_name' => $apellido,
            'phone' => '5551234567',
            'brand_id' => $marca->id,
            'vehicle_model_id' => $modelo->id,
            'year' => 2020,
            'plate' => 'TW-' . strtoupper(substr($apellido, 0, 4)),
            'fuel_level' => '1/2',
            'vehicle_type_id' => VehicleType::where('code', 'automobile')->firstOrFail()->id,
            'witnesses' => $testigos,
            'damages' => [],
        ];
    }

    private function registrar(array $testigos, string $apellido = 'Testigo'): Recepcion
    {
        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), $this->datosNueva($testigos, $apellido))
            ->assertSessionHasNoErrors();

        return Recepcion::latest('id')->firstOrFail();
    }

    // 1-2 · Seeder: crea los 7 y es idempotente

    public function test_seeder_crea_los_siete_testigos(): void
    {
        $this->seed(WarningLightSeeder::class);

        $this->assertSame(
            ['engine', 'abs', 'oil', 'battery', 'temp', 'brake', 'tpms'],
            WarningLight::orderBy('sort')->pluck('code')->all(),
        );
    }

    public function test_seeder_es_idempotente_y_no_sobrescribe_cambios_administrativos(): void
    {
        $this->seed(WarningLightSeeder::class);
        WarningLight::where('code', 'brake')->update(['name' => 'Frenos (ajustado)']);

        $this->seed(WarningLightSeeder::class);

        $this->assertSame(7, WarningLight::count());
        $this->assertSame('Frenos (ajustado)', WarningLight::where('code', 'brake')->value('name'));
    }

    // 3-5 · Códigos

    public function test_brake_y_tpms_existen(): void
    {
        $this->seed(WarningLightSeeder::class);

        $this->assertTrue(WarningLight::where('code', 'brake')->exists());
        $this->assertTrue(WarningLight::where('code', 'tpms')->exists());
    }

    public function test_abs_y_brake_son_codigos_distintos(): void
    {
        $this->seed(WarningLightSeeder::class);

        $this->assertNotSame('abs', 'brake');
        $this->assertSame('ABS', WarningLight::where('code', 'abs')->value('name'));
        $this->assertSame('Freno', WarningLight::where('code', 'brake')->value('name'));
    }

    // 6-8 · Nueva recepción acepta FRENO, TPMS y combinaciones

    public function test_nueva_recepcion_acepta_brake(): void
    {
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);

        $this->assertSame(['brake'], $this->registrar(['brake'], 'Freno')->witnesses);
    }

    public function test_nueva_recepcion_acepta_tpms(): void
    {
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);

        $this->assertSame(['tpms'], $this->registrar(['tpms'], 'Tpms')->witnesses);
    }

    public function test_acepta_abs_freno_y_tpms_simultaneamente(): void
    {
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);

        $r = $this->registrar(['abs', 'brake', 'tpms'], 'Varios');

        $this->assertSame(['abs', 'brake', 'tpms'], $r->witnesses);
    }

    // 9 · Código inexistente

    public function test_rechaza_codigo_inexistente(): void
    {
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), $this->datosNueva(['turbo'], 'Inexistente'))
            ->assertSessionHasErrors('witnesses.0');

        $this->assertDatabaseCount('recepcions', 0);
    }

    // 10 · Histórico con códigos antiguos

    public function test_historico_con_codigos_antiguos_sigue_funcionando(): void
    {
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);
        $r = Recepcion::factory()->create(['witnesses' => ['engine', 'abs']]);

        $this->actingAs($this->admin())->get(route('recepcion.show', $r))->assertOk();
        $this->actingAs($this->admin())->get(route('recepcion.edit', $r))->assertOk();
        $this->assertSame(['engine', 'abs'], $r->fresh()->witnesses);
    }

    // 11 · Show usa nombres correctos (i18n) y recibe los códigos sin traducir

    public function test_show_recibe_brake_y_tpms(): void
    {
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);
        $r = $this->registrar(['brake', 'tpms'], 'Show');

        $props = $this->actingAs($this->admin())
            ->get(route('recepcion.show', $r))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertEqualsCanonicalizing(['brake', 'tpms'], $props['recepcion']['witnesses']);
    }

    public function test_i18n_tiene_etiquetas_de_freno_y_tpms_en_ambos_idiomas(): void
    {
        $es = file_get_contents(resource_path('js/i18n/es.js'));
        $en = file_get_contents(resource_path('js/i18n/en.js'));

        foreach (['brake', 'tpms'] as $codigo) {
            $this->assertStringContainsString("{$codigo}:", $es);
            $this->assertStringContainsString("{$codigo}:", $en);
        }
        $this->assertStringContainsString("brake:          'FRENO'", $es);
        $this->assertStringContainsString("brake:          'BRAKE'", $en);
    }

    // 12 · PDF soporta Freno y TPMS

    public function test_pdf_incluye_freno_y_tpms(): void
    {
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);
        $r = $this->registrar(['abs', 'brake', 'tpms'], 'Pdf');

        $this->actingAs($this->admin())
            ->get(route('recepcion.pdf', $r))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $html = view('pdf.recepcion', [
            'recepcion' => $r->load(['client', 'vehicle.brand', 'vehicle.vehicleModel']),
            'settings' => Setting::current(),
            'testigos' => WarningLight::orderBy('sort')->get(['code', 'name', 'active']),
        ])->render();

        // Etiquetas desde el catálogo (nombre en mayúsculas), no desde una lista fija en el PDF
        $this->assertStringContainsString('FRENO', $html);
        $this->assertStringContainsString('TPMS', $html);
        $this->assertStringContainsString('MOTOR', $html);
    }

    // 13 · Testigo desactivado no aparece para nueva recepción

    public function test_testigo_desactivado_no_aparece_para_nueva_recepcion(): void
    {
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);
        WarningLight::where('code', 'temp')->update(['active' => false]);

        $props = $this->actingAs($this->admin())
            ->get(route('recepcion.create'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertNotContains('temp', collect($props['warningLights'])->pluck('code')->all());
        $this->assertContains('brake', collect($props['warningLights'])->pluck('code')->all());

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), $this->datosNueva(['temp'], 'Desactivado'))
            ->assertSessionHasErrors('witnesses.0');
    }

    // 14 · Histórico con testigo desactivado sigue mostrándose

    public function test_historico_con_testigo_desactivado_sigue_mostrandose(): void
    {
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);
        $r = Recepcion::factory()->create(['witnesses' => ['temp']]);
        WarningLight::where('code', 'temp')->update(['active' => false]);

        $props = $this->actingAs($this->admin())
            ->get(route('recepcion.edit', $r))
            ->assertOk()
            ->viewData('page')['props'];
        $this->assertContains('temp', collect($props['warningLights'])->pluck('code')->all());

        $this->actingAs($this->admin())
            ->get(route('recepcion.show', $r))
            ->assertOk();
        $this->assertSame(['temp'], $r->fresh()->witnesses);
    }

    // Extensibilidad: un testigo nuevo es solo una fila en warning_lights

    public function test_testigo_nuevo_sin_migracion_aparece_en_nueva_recepcion(): void
    {
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);
        WarningLight::create(['code' => 'dpf', 'name' => 'DPF', 'icon' => 'dpf', 'sort' => 20, 'active' => true]);

        $props = $this->actingAs($this->admin())
            ->get(route('recepcion.create'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertContains('dpf', collect($props['warningLights'])->pluck('code')->all());

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), $this->datosNueva(['dpf'], 'Dpf'))
            ->assertSessionHasNoErrors();
    }

    public function test_icono_desconocido_cae_en_warning_sin_romper(): void
    {
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);
        $testigo = WarningLight::create(['code' => 'airbag', 'name' => 'Airbag', 'icon' => 'no-existe', 'sort' => 21, 'active' => true]);

        $this->assertSame('warning', $testigo->iconoSeguro());
        $this->assertSame('warning', WarningLight::create(['code' => 'esc', 'name' => 'ESC', 'icon' => 'x', 'sort' => 22, 'active' => true])->iconoSeguro());
    }

    public function test_pdf_no_falla_con_testigo_de_icono_desconocido(): void
    {
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);
        WarningLight::create(['code' => 'airbag', 'name' => 'Airbag', 'icon' => 'no-existe', 'sort' => 21, 'active' => true]);
        $r = $this->registrar(['airbag'], 'Airbag');

        $html = view('pdf.recepcion', [
            'recepcion' => $r->load(['client', 'vehicle.brand', 'vehicle.vehicleModel']),
            'settings' => Setting::current(),
            'testigos' => WarningLight::orderBy('sort')->get(['code', 'name', 'icon', 'active']),
        ])->render();

        $this->assertStringContainsString('AIRBAG', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('images/icons', $html);
    }

    public function test_registro_de_iconos_php_y_vue_tienen_las_mismas_claves(): void
    {
        $js = file_get_contents(resource_path('js/Components/Icons/registroTestigos.js'));
        preg_match_all('/^\s+(\w+): Icon\w+,$/m', $js, $coincidencias);

        $this->assertEqualsCanonicalizing(\App\Support\IconosTestigo::CLAVES, $coincidencias[1]);
    }

    public function test_condition_fields_no_tiene_condicionales_por_codigo_de_testigo(): void
    {
        $vue = file_get_contents(resource_path('js/Components/Recepciones/ReceptionConditionFields.vue'));

        foreach (['engine', 'abs', 'oil', 'battery', 'temp', 'brake', 'tpms'] as $codigo) {
            $this->assertStringNotContainsString("w.code === '{$codigo}'", $vue);
            $this->assertStringNotContainsString("'{$codigo}'", $vue);
        }
        $this->assertStringNotContainsString('switch', $vue);
    }

    public function test_show_muestra_nombre_de_catalogo_para_testigo_sin_i18n(): void
    {
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);
        WarningLight::create(['code' => 'dpf', 'name' => 'DPF', 'icon' => 'dpf', 'sort' => 20, 'active' => true]);
        $r = Recepcion::factory()->create(['witnesses' => ['dpf']]);

        $props = $this->actingAs($this->admin())
            ->get(route('recepcion.show', $r))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('DPF', $props['nombresTestigos']['dpf']);
        $this->assertSame('Freno', $props['nombresTestigos']['brake']);
    }

    // ── Icono de biblioteca vs personalizado ───────────────────────────────

    private function pngEnDisco(string $ruta, int $lado = 256): void
    {
        $img = imagecreatetruecolor($lado, $lado);
        $tmp = tempnam(sys_get_temp_dir(), 'ico');
        imagepng($img, $tmp);
        Storage::disk('local')->put($ruta, file_get_contents($tmp));
        @unlink($tmp);
    }

    private function hex32(): string
    {
        return str_repeat('a', 32);
    }

    public function test_columnas_de_icono_existen_por_migracion_incremental(): void
    {
        $this->assertTrue(Schema::hasColumn('warning_lights', 'icon'));
        $this->assertTrue(Schema::hasColumn('warning_lights', 'custom_icon_path'));
    }

    public function test_icono_personalizado_valido_tiene_prioridad_sobre_biblioteca(): void
    {
        Storage::fake('local');
        $this->seed(WarningLightSeeder::class);
        $ruta = 'testigos/' . $this->hex32() . '.png';
        $this->pngEnDisco($ruta);
        $testigo = WarningLight::where('code', 'brake')->firstOrFail();
        $testigo->update(['custom_icon_path' => $ruta]);

        $fuente = $testigo->fresh()->fuenteIcono();

        $this->assertSame('personalizado', $fuente['tipo']);
        $this->assertStringEndsWith($ruta, str_replace('\\', '/', $fuente['ruta']));
    }

    public function test_sin_icono_personalizado_usa_biblioteca_y_luego_warning(): void
    {
        $this->seed(WarningLightSeeder::class);

        $this->assertSame(['tipo' => 'biblioteca', 'clave' => 'brake'], WarningLight::where('code', 'brake')->first()->fuenteIcono());

        $sinIcono = WarningLight::create(['code' => 'x1', 'name' => 'X', 'icon' => 'no-existe', 'sort' => 90, 'active' => true]);
        $this->assertSame(['tipo' => 'biblioteca', 'clave' => 'warning'], $sinIcono->fuenteIcono());
    }

    public function test_ruta_personalizada_arbitraria_se_ignora(): void
    {
        Storage::fake('local');
        foreach (['../../.env', 'https://evil.test/x.png', 'testigos/../secreto.png', '/etc/passwd', 'testigos/' . str_repeat('a', 32) . '.svg'] as $maliciosa) {
            $t = WarningLight::create(['code' => 'm' . md5($maliciosa), 'name' => 'M', 'icon' => 'engine', 'custom_icon_path' => $maliciosa, 'sort' => 91, 'active' => true]);

            $this->assertNull($t->rutaIconoPersonalizado(), $maliciosa);
            $this->assertSame('biblioteca', $t->fuenteIcono()['tipo'], $maliciosa);
        }
    }

    public function test_personalizado_desactivado_no_aparece_en_nueva_pero_sigue_en_historico(): void
    {
        Storage::fake('local');
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);
        $ruta = 'testigos/' . $this->hex32() . '.png';
        $this->pngEnDisco($ruta);
        WarningLight::create(['code' => 'dpf2', 'name' => 'DPF', 'icon' => 'warning', 'custom_icon_path' => $ruta, 'sort' => 30, 'active' => true]);
        $r = $this->registrar(['dpf2'], 'Histo');
        WarningLight::where('code', 'dpf2')->update(['active' => false]);

        $nueva = $this->actingAs($this->admin())->get(route('recepcion.create'))->viewData('page')['props'];
        $this->assertNotContains('dpf2', collect($nueva['warningLights'])->pluck('code')->all());

        $edicion = $this->actingAs($this->admin())->get(route('recepcion.edit', $r))->viewData('page')['props'];
        $fila = collect($edicion['warningLights'])->firstWhere('code', 'dpf2');
        $this->assertNotNull($fila);
        $this->assertFalse((bool) $fila['active']);
        $this->assertFileExists(Storage::disk('local')->path($ruta));
    }

    public function test_no_se_borra_un_testigo_usado_por_recepciones(): void
    {
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);
        $this->registrar(['brake'], 'Usado');

        $this->expectException(\DomainException::class);
        WarningLight::where('code', 'brake')->firstOrFail()->delete();
    }

    public function test_borrar_testigo_sin_uso_elimina_su_icono_personalizado(): void
    {
        Storage::fake('local');
        $ruta = 'testigos/' . $this->hex32() . '.png';
        $this->pngEnDisco($ruta);
        $t = WarningLight::create(['code' => 'borrable', 'name' => 'B', 'icon' => 'warning', 'custom_icon_path' => $ruta, 'sort' => 92, 'active' => true]);

        $t->delete();

        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_pdf_usa_icono_personalizado_antes_que_biblioteca(): void
    {
        Storage::fake('local');
        $this->seed([VehicleCatalogSeeder::class, WarningLightSeeder::class]);
        $ruta = 'testigos/' . $this->hex32() . '.png';
        $this->pngEnDisco($ruta);
        WarningLight::create(['code' => 'propio', 'name' => 'Propio', 'icon' => 'warning', 'custom_icon_path' => $ruta, 'sort' => 40, 'active' => true]);
        $r = $this->registrar(['propio'], 'Pdf2');

        $html = view('pdf.recepcion', [
            'recepcion' => $r->load(['client', 'vehicle.brand', 'vehicle.vehicleModel']),
            'settings' => Setting::current(),
            'testigos' => WarningLight::orderBy('sort')->get(['code', 'name', 'icon', 'custom_icon_path', 'active']),
        ])->render();

        $this->assertStringContainsString('data:image/png;base64,' . base64_encode(Storage::disk('local')->get($ruta)), $html);
        $this->assertStringContainsString('PROPIO', $html);
    }

    // ── Reglas de un icono personalizado (validación en servidor) ───────────

    private function archivoTmp(callable $generar, string $extension = 'png'): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'val') . '.' . $extension;
        $generar($tmp);

        return $tmp;
    }

    public function test_acepta_png_webp_y_jpg_de_256(): void
    {
        $png = $this->archivoTmp(fn ($p) => imagepng(imagecreatetruecolor(256, 256), $p));
        $webp = $this->archivoTmp(fn ($p) => imagewebp(imagecreatetruecolor(256, 256), $p), 'webp');
        $jpg = $this->archivoTmp(fn ($p) => imagejpeg(imagecreatetruecolor(256, 256), $p), 'jpg');

        $this->assertSame([], IconoPersonalizado::errores($png));
        $this->assertSame([], IconoPersonalizado::errores($webp));
        $this->assertSame([], IconoPersonalizado::errores($jpg));
    }

    public function test_rechaza_svg_html_y_ejecutables_aunque_tengan_extension_png(): void
    {
        $svg = tempnam(sys_get_temp_dir(), 'svg') . '.png';
        file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><script>alert(1)</script></svg>');
        $html = tempnam(sys_get_temp_dir(), 'html') . '.png';
        file_put_contents($html, '<html><body><script>alert(1)</script></body></html>');
        $exe = tempnam(sys_get_temp_dir(), 'exe') . '.png';
        file_put_contents($exe, "MZ\x90\x00\x03\x00\x00\x00" . str_repeat("\x00", 200));

        $this->assertNotEmpty(IconoPersonalizado::errores($svg));
        $this->assertNotEmpty(IconoPersonalizado::errores($html));
        $this->assertNotEmpty(IconoPersonalizado::errores($exe));
    }

    public function test_rechaza_dimensiones_fuera_de_rango_y_no_cuadradas(): void
    {
        $pequena = $this->archivoTmp(fn ($p) => imagepng(imagecreatetruecolor(63, 63), $p));
        $grande = $this->archivoTmp(fn ($p) => imagepng(imagecreatetruecolor(1025, 1025), $p));
        $rectangular = $this->archivoTmp(fn ($p) => imagepng(imagecreatetruecolor(128, 256), $p));

        $this->assertNotEmpty(IconoPersonalizado::errores($pequena));
        $this->assertNotEmpty(IconoPersonalizado::errores($grande));
        $this->assertNotEmpty(IconoPersonalizado::errores($rectangular));
    }

    public function test_rechaza_peso_mayor_a_500_kb(): void
    {
        $pesada = $this->archivoTmp(function ($p) {
            $img = imagecreatetruecolor(1024, 1024);
            for ($y = 0; $y < 1024; $y += 1) {
                for ($x = 0; $x < 1024; $x += 1) {
                    imagesetpixel($img, $x, $y, random_int(0, 0xFFFFFF));
                }
            }
            imagepng($img, $p, 0);
        });

        $this->assertGreaterThan(IconoPersonalizado::MAX_BYTES, filesize($pesada));
        $this->assertNotEmpty(IconoPersonalizado::errores($pesada));
    }
}
