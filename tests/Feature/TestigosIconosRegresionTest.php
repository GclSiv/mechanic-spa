<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Recepcion;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarningLight;
use App\Support\IconosTestigo;
use Database\Seeders\WarningLightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión: en Nueva recepción todos los testigos mostraban el icono genérico "warning".
 * Causa: testigos creados sin icono quedaban con el default 'warning' y el seeder no reconciliaba.
 * Esta prueba comprueba el mapping real que recibe la UI (props de recepcion.create), no solo el registro.
 */
class TestigosIconosRegresionTest extends TestCase
{
    use RefreshDatabase;

    /** código => clave de icono esperada (siete testigos oficiales). */
    private const OFICIALES = [
        'engine' => 'engine',
        'abs' => 'abs',
        'oil' => 'oil',
        'battery' => 'battery',
        'temp' => 'temp',
        'brake' => 'brake',
        'tpms' => 'tpms',
    ];

    public function test_los_siete_testigos_oficiales_resuelven_su_clave_distinta_de_warning(): void
    {
        $this->seed(WarningLightSeeder::class);

        foreach (self::OFICIALES as $codigo => $claveEsperada) {
            $icono = WarningLight::where('code', $codigo)->value('icon');

            $this->assertSame($claveEsperada, $icono, "{$codigo} debe tener icon={$claveEsperada}");
            $this->assertSame($claveEsperada, IconosTestigo::resolver($icono), "{$codigo} debe resolver a {$claveEsperada}");
            $this->assertNotSame('warning', IconosTestigo::resolver($icono), "{$codigo} no debe caer en warning");
        }
    }

    public function test_registros_legacy_con_icono_warning_se_reconcilian_sin_tocar_nombre_ni_orden(): void
    {
        // Estado real del fallo: filas creadas sin icono (default 'warning') y con cambios administrativos.
        foreach (self::OFICIALES as $codigo => $clave) {
            WarningLight::create(['code' => $codigo, 'name' => 'Nombre ' . $codigo, 'sort' => 99, 'active' => true, 'icon' => 'warning']);
        }

        $this->seed(WarningLightSeeder::class);

        foreach (self::OFICIALES as $codigo => $clave) {
            $w = WarningLight::where('code', $codigo)->firstOrFail();
            $this->assertSame($clave, $w->icon, "{$codigo} debe reconciliarse a {$clave}");
            $this->assertSame('Nombre ' . $codigo, $w->name, 'el nombre administrativo no se sobrescribe');
            $this->assertSame(99, $w->sort, 'el orden administrativo no se sobrescribe');
        }
    }

    public function test_la_reconciliacion_es_idempotente(): void
    {
        WarningLight::create(['code' => 'engine', 'name' => 'Motor', 'sort' => 1, 'active' => true]); // icon = default 'warning'

        $seeder = new WarningLightSeeder();
        $this->assertGreaterThan(0, $seeder->reconciliarIconos());
        $this->assertSame('engine', WarningLight::where('code', 'engine')->value('icon'));
        $this->assertSame(0, $seeder->reconciliarIconos(), 'una segunda ejecución no corrige nada');
    }

    public function test_un_icono_valido_ya_elegido_no_se_sobrescribe(): void
    {
        WarningLight::create(['code' => 'engine', 'name' => 'Motor', 'sort' => 1, 'active' => true, 'icon' => 'oil']);

        $this->seed(WarningLightSeeder::class);

        $this->assertSame('oil', WarningLight::where('code', 'engine')->value('icon'), 'solo warning o NULL se corrige');
    }

    public function test_testigos_del_cliente_no_se_tocan_al_reconciliar(): void
    {
        WarningLight::create(['code' => 'airbag', 'name' => 'Airbag', 'sort' => 30, 'active' => true, 'icon' => 'warning']);
        WarningLight::create(['code' => 'llave', 'name' => 'Llave', 'sort' => 31, 'active' => true, 'icon' => 'brake']);

        $this->seed(WarningLightSeeder::class);

        $this->assertSame('warning', WarningLight::where('code', 'airbag')->value('icon'));
        $this->assertSame('brake', WarningLight::where('code', 'llave')->value('icon'));
    }

    public function test_la_pantalla_de_nueva_recepcion_recibe_la_clave_correcta_por_testigo(): void
    {
        $this->seed(WarningLightSeeder::class);
        // Simula un icono desconocido: debe llegar a la UI como clave y resolverse a warning en el registro.
        WarningLight::create(['code' => 'desconocido', 'name' => 'Desconocido', 'sort' => 40, 'active' => true, 'icon' => 'no-existe']);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $page = $this->actingAs($admin)->get(route('recepcion.create'))->assertOk()->viewData('page');

        $recibidos = collect($page['props']['warningLights'])->pluck('icon', 'code')->all();

        foreach (self::OFICIALES as $codigo => $clave) {
            $this->assertSame($clave, $recibidos[$codigo] ?? null, "la UI debe recibir icon={$clave} para {$codigo}");
        }
        $this->assertSame('no-existe', $recibidos['desconocido']);
        $this->assertSame('warning', IconosTestigo::resolver($recibidos['desconocido']));
    }

    public function test_el_registro_vue_apunta_cada_clave_oficial_a_su_componente(): void
    {
        $js = file_get_contents(resource_path('js/Components/Icons/registroTestigos.js'));

        $esperado = [
            'engine' => 'IconEngine', 'abs' => 'IconAbs', 'oil' => 'IconOil', 'battery' => 'IconBattery',
            'temp' => 'IconTemp', 'brake' => 'IconBrake', 'tpms' => 'IconTpms', 'warning' => 'IconWarning',
        ];

        foreach ($esperado as $clave => $componente) {
            $this->assertMatchesRegularExpression("/^\s*{$clave}:\s*{$componente},/m", $js, "{$clave} debe apuntar a {$componente}");
        }
        $this->assertStringContainsString('ICONOS_TESTIGO[clave] ?? ICONOS_TESTIGO.warning', $js, 'fallback solo tras resolver la clave');
    }

    private function instantanea(): array
    {
        return WarningLight::orderBy('code')
            ->get(['code', 'name', 'icon', 'sort', 'active', 'custom_icon_path'])
            ->map(fn ($w) => $w->only(['code', 'name', 'icon', 'sort', 'active', 'custom_icon_path']))
            ->all();
    }

    public function test_segunda_ejecucion_del_seeder_no_cambia_ninguna_fila(): void
    {
        // Mezcla real: legacy 'warning' en los siete, un testigo de cliente y un icono válido ya elegido.
        foreach (self::OFICIALES as $codigo => $clave) {
            WarningLight::create(['code' => $codigo, 'name' => 'Nombre ' . $codigo, 'sort' => 50, 'active' => true, 'icon' => 'warning']);
        }
        WarningLight::create(['code' => 'airbag', 'name' => 'Airbag', 'sort' => 60, 'active' => true, 'icon' => 'warning']);

        $this->seed(WarningLightSeeder::class);
        $primera = $this->instantanea();

        $this->seed(WarningLightSeeder::class);

        $this->assertSame($primera, $this->instantanea(), 'la segunda ejecución no modifica ninguna fila');
        $this->assertSame(0, (new WarningLightSeeder())->reconciliarIconos());
    }

    public function test_testigo_personalizado_conserva_icono_ruta_nombre_orden_y_activo(): void
    {
        WarningLight::create([
            'code' => 'airbag', 'name' => 'Airbag', 'sort' => 60, 'active' => false,
            'icon' => 'warning', 'custom_icon_path' => 'warning-lights/custom/airbag.png',
        ]);

        $this->seed(WarningLightSeeder::class);

        $airbag = WarningLight::where('code', 'airbag')->firstOrFail();
        $this->assertSame('warning', $airbag->icon);
        $this->assertSame('warning-lights/custom/airbag.png', $airbag->custom_icon_path);
        $this->assertSame('Airbag', $airbag->name);
        $this->assertSame(60, $airbag->sort);
        $this->assertFalse((bool) $airbag->active);
    }

    public function test_el_seeder_no_modifica_witnesses_de_las_recepciones(): void
    {
        $cliente = Client::factory()->create();
        $vehiculo = Vehicle::factory()->create(['client_id' => $cliente->id]);
        $recepcion = Recepcion::factory()->create([
            'client_id' => $cliente->id,
            'vehicle_id' => $vehiculo->id,
            'witnesses' => ['engine', 'airbag'],
        ]);
        WarningLight::create(['code' => 'engine', 'name' => 'Motor', 'sort' => 1, 'active' => true, 'icon' => 'warning']);

        $this->seed(WarningLightSeeder::class);

        $this->assertSame(['engine', 'airbag'], $recepcion->fresh()->witnesses);
        $this->assertSame('engine', WarningLight::where('code', 'engine')->value('icon'));
    }
}
