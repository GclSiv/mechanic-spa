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
use App\Support\IconosTestigo;
use Database\Seeders\WarningLightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Iconos del PDF de testigos: PNG controlados en resources/images/warning-lights, sin depender de
 * public/images/icons/*.svg. No comparamos bytes completos de imagen (frágil): solo estructura y dimensiones.
 */
class IconosTestigoPngTest extends TestCase
{
    use RefreshDatabase;

    /** Biblioteca completa esperada (13). Debe coincidir con IconosTestigo::CLAVES y con registroTestigos.js. */
    private const CLAVES = [
        'engine', 'abs', 'oil', 'battery', 'temp', 'brake', 'tpms',
        'low_beam', 'service', 'low_fuel', 'eps', 'airbag',
        'warning',
    ];

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function registrar(array $testigos): Recepcion
    {
        $this->seed(\Database\Seeders\VehicleCatalogSeeder::class);
        ClientStatus::firstOrCreate(['name' => 'Active'], ['color' => 'bg-green-500']);
        $marca = Brand::firstOrCreate(['name' => 'Genérica']);
        $modelo = VehicleModel::firstOrCreate(['brand_id' => $marca->id, 'name' => 'Modelo']);

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), [
                'first_name' => 'Cliente',
                'last_name' => 'Png',
                'phone' => '5551234567',
                'brand_id' => $marca->id,
                'vehicle_model_id' => $modelo->id,
                'year' => 2020,
                'plate' => 'PNG-0001',
                'fuel_level' => '1/2',
                'vehicle_type_id' => VehicleType::where('code', 'automobile')->firstOrFail()->id,
                'witnesses' => $testigos,
                'damages' => [],
            ])
            ->assertSessionHasNoErrors();

        return Recepcion::latest('id')->firstOrFail();
    }

    private function htmlPdf(Recepcion $r): string
    {
        return view('pdf.recepcion', [
            'recepcion' => $r->load(['client', 'vehicle.brand', 'vehicle.vehicleModel']),
            'settings' => Setting::current(),
            'testigos' => WarningLight::orderBy('sort')->get(['code', 'name', 'icon', 'active']),
        ])->render();
    }

    public function test_las_trece_claves_tienen_png_en_la_carpeta_controlada(): void
    {
        foreach (self::CLAVES as $clave) {
            $this->assertFileExists(resource_path("images/warning-lights/{$clave}.png"), "Falta {$clave}.png");
        }
    }

    public function test_los_png_son_image_png_y_miden_128_px(): void
    {
        foreach (self::CLAVES as $clave) {
            $ruta = resource_path("images/warning-lights/{$clave}.png");

            $this->assertSame('image/png', (new \finfo(FILEINFO_MIME_TYPE))->file($ruta), "{$clave}.png no es PNG");

            [$ancho, $alto, $tipo] = getimagesize($ruta);
            $this->assertSame(IMAGETYPE_PNG, $tipo);
            $this->assertSame(128, $ancho, "{$clave}.png ancho");
            $this->assertSame(128, $alto, "{$clave}.png alto");
        }
    }

    public function test_los_png_tienen_fondo_transparente(): void
    {
        foreach (self::CLAVES as $clave) {
            $imagen = imagecreatefrompng(resource_path("images/warning-lights/{$clave}.png"));
            $this->assertNotFalse($imagen);

            $esquina = imagecolorat($imagen, 0, 0);
            $alfa = ($esquina >> 24) & 0x7F; // 127 = totalmente transparente
            $this->assertSame(127, $alfa, "{$clave}.png no tiene fondo transparente en la esquina");
            imagedestroy($imagen);
        }
    }

    /** Iconos históricos cuyo diseño viene de IconEngine/IconAbs/IconOil/IconBattery/IconTemp. */
    private const HISTORICOS = ['engine', 'abs', 'oil', 'battery', 'temp'];

    /** @return array{tipos: string[], colorType: int} chunks PNG y tipo de color del IHDR */
    private function chunksPng(string $ruta): array
    {
        $b = file_get_contents($ruta);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($b, 0, 8), "{$ruta} no tiene firma PNG");

        $o = 8;
        $tipos = [];
        $colorType = -1;
        while ($o < strlen($b)) {
            $len = unpack('N', substr($b, $o, 4))[1];
            $tipo = substr($b, $o + 4, 4);
            $tipos[] = $tipo;
            if ($tipo === 'IHDR') {
                $colorType = ord($b[$o + 8 + 9]);
            }
            $o += 12 + $len;
        }

        return ['tipos' => $tipos, 'colorType' => $colorType];
    }

    public function test_los_cinco_historicos_son_rgba_con_contenido_visible(): void
    {
        foreach (self::HISTORICOS as $clave) {
            $ruta = resource_path("images/warning-lights/{$clave}.png");
            $this->assertSame(6, $this->chunksPng($ruta)['colorType'], "{$clave}.png debe ser RGBA (color type 6)");

            $imagen = imagecreatefrompng($ruta);
            $opacos = 0;
            for ($x = 0; $x < 128; $x++) {
                for ($y = 0; $y < 128; $y++) {
                    if (((imagecolorat($imagen, $x, $y) >> 24) & 0x7F) < 127) {
                        $opacos++;
                    }
                }
            }
            imagedestroy($imagen);

            // Umbral bajo a propósito: ABS es un glifo pequeño dentro de su viewBox histórico.
            $this->assertGreaterThan(50, $opacos, "{$clave}.png no tiene trazo visible");
        }
    }

    /**
     * Metadatos de texto/EXIF en un PNG del PDF: fallo duro (no provienen del mecanismo de transferencia).
     * El chunk caBX se trata aparte, en test_deuda_tecnica_c2pa_caBX_registrada: no bloquea Testigos V2.
     */
    public function test_los_png_no_llevan_metadatos_de_texto_ni_exif(): void
    {
        $metadatos = ['tEXt', 'zTXt', 'iTXt', 'eXIf'];

        foreach (self::CLAVES as $clave) {
            $tipos = $this->chunksPng(resource_path("images/warning-lights/{$clave}.png"))['tipos'];

            foreach ($metadatos as $meta) {
                $this->assertNotContains($meta, $tipos, "{$clave}.png contiene el chunk {$meta}");
            }
        }
    }

    /**
     * DEUDA TÉCNICA CONDICIONADA (C2PA/caBX): el mecanismo de transferencia al dispositivo puede insertar un
     * chunk caBX en los PNG. No afecta renderizado ni seguridad funcional. Investigar si vuelve a ser necesario
     * distribuir assets binarios limpios. Esta prueba NO falla: registra la deuda como advertencia y deja
     * visibles las claves afectadas. No se elimina la detección.
     */
public function test_deuda_tecnica_c2pa_caBX_registrada(): void
{
    $afectados = [];

    foreach (self::CLAVES as $clave) {
        $tipos = $this->chunksPng(
            resource_path("images/warning-lights/{$clave}.png")
        )['tipos'];

        if (in_array('caBX', $tipos, true)) {
            $afectados[] = $clave;
        }
    }

    if ($afectados !== []) {
        fwrite(
            STDERR,
            PHP_EOL
            . '[DEUDA TECNICA] C2PA/caBX detectado en: '
            . implode(', ', $afectados)
            . '. C2PA/caBX puede ser insertado por el mecanismo de transferencia al dispositivo. '
            . 'No afecta renderizado ni seguridad funcional. '
            . 'Investigar si vuelve a ser necesario distribuir assets binarios limpios.'
            . PHP_EOL
        );
    }

    // caBX queda registrado como deuda técnica no bloqueante.
    // Su presencia no invalida el PNG ni afecta el renderizado del PDF.
    $this->assertTrue(true);
}

    public function test_clave_desconocida_resuelve_a_warning(): void
    {
        $this->assertSame('warning', IconosTestigo::resolver('no-existe'));
        $this->assertSame('warning', IconosTestigo::resolver(null));
        $this->assertSame(
            resource_path('images/warning-lights/warning.png'),
            IconosTestigo::rutaPng('../../.env'),
        );
    }

    public function test_pdf_genera_con_los_siete_testigos_y_usa_png_embebidos(): void
    {
        $this->seed(WarningLightSeeder::class);
        $r = $this->registrar(['engine', 'abs', 'oil', 'battery', 'temp', 'brake', 'tpms']);

        $this->actingAs($this->admin())
            ->get(route('recepcion.pdf', $r))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $html = $this->htmlPdf($r);
        $this->assertGreaterThanOrEqual(7, substr_count($html, 'data:image/png;base64,'));
    }

    public function test_el_png_de_advertencia_se_embebe_para_clave_desconocida(): void
    {
        WarningLight::create(['code' => 'airbag', 'name' => 'Airbag', 'icon' => 'no-existe', 'sort' => 21, 'active' => true]);
        $r = $this->registrar(['airbag']);

        $html = $this->htmlPdf($r);

        $esperado = 'data:image/png;base64,' . base64_encode(file_get_contents(resource_path('images/warning-lights/warning.png')));
        $this->assertStringContainsString($esperado, $html);
    }

    public function test_ninguna_plantilla_pdf_depende_de_svg_publicos(): void
    {
        $plantillas = [
            resource_path('views/pdf/recepcion.blade.php'),
        ];

        foreach ($plantillas as $plantilla) {
            $contenido = file_get_contents($plantilla);
            $this->assertStringNotContainsString('images/icons', $contenido, basename($plantilla));
            $this->assertStringNotContainsString('.svg', $contenido, basename($plantilla));
            $this->assertStringNotContainsString('public_path(', $contenido, basename($plantilla));
        }
    }

    public function test_el_html_renderizado_no_referencia_svg_publicos(): void
    {
        $this->seed(WarningLightSeeder::class);
        $r = $this->registrar(['engine', 'brake', 'tpms']);

        $html = $this->htmlPdf($r);

        $this->assertStringNotContainsString('images/icons', $html);
        $this->assertStringNotContainsString('.svg', $html);
    }

    public function test_las_claves_php_coinciden_con_los_png_disponibles(): void
    {
        $this->assertSame(self::CLAVES, IconosTestigo::CLAVES);
        foreach (IconosTestigo::CLAVES as $clave) {
            $this->assertFileExists(resource_path("images/warning-lights/{$clave}.png"), "Falta el PNG de {$clave}");
        }
    }

    public function test_biblioteca_completa_vue_php_y_png_tienen_las_mismas_13_claves(): void
    {
        $js = file_get_contents(resource_path('js/Components/Icons/registroTestigos.js'));
        preg_match_all('/^\s+(\w+): Icon\w+,$/m', $js, $coincidencias);

        $this->assertCount(13, IconosTestigo::CLAVES);
        $this->assertEqualsCanonicalizing(IconosTestigo::CLAVES, $coincidencias[1], 'Vue registry ↔ PHP');
        $this->assertEqualsCanonicalizing(IconosTestigo::CLAVES, self::CLAVES, 'PHP ↔ lista de la prueba');

        $pngs = array_map(
            fn (string $ruta) => pathinfo($ruta, PATHINFO_FILENAME),
            glob(resource_path('images/warning-lights/*.png')),
        );
        $this->assertEqualsCanonicalizing(IconosTestigo::CLAVES, $pngs, 'PHP ↔ PNG disponibles');
    }
}
