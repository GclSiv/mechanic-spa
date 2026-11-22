<?php

namespace Tests\Feature;

use App\Actions\Quotes\CreateQuoteAction;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\Setting;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * EMPRESA-1.3: documentos PDF con una sola fuente de identidad empresarial.
 */
class EmpresaDocumentosTest extends TestCase
{
    use RefreshDatabase;

    /** Leyenda obligatoria del ticket de pago: deja claro que no es CFDI ni factura. */
    private const LEYENDA_TICKET = 'Comprobante interno de pago. No es un CFDI ni sustituye una factura fiscal.';

    /** Cláusula legal por defecto de recepción. Debe permanecer idéntica a la original. */
    private const CLAUSULA_LEGAL_ORIGINAL = 'You are entitled by law to the return of all parts replaced, except those for which there is a core charge. STORAGE FEES: Vehicles not picked up within 48 hours of completion are subject to a $50/day storage fee. I authorize the above repairs and employees to operate the vehicle for testing.';

    /** Marcas y etiquetas heredadas que no deben volver a las plantillas ni a los controladores. */
    private const PROHIBIDOS = ['Mi taller', 'Taller.png', 'Setting::first', 'Phone:', 'Terms:', 'JK', 'CarbaFix'];

    private function empresa(array $extra = []): Setting
    {
        Setting::current()->update(array_merge([
            'company_name' => 'Taller Real',
            'razon_social' => 'Taller Real SA de CV',
            'rfc'          => 'TRE010101AAA',
            'address'      => 'Calle Uno 1',
            'phone'        => '9511112222',
            'email'        => 'contacto@taller.example',
        ], $extra));

        return Setting::current();
    }

    private function orden(): RepairOrder
    {
        $client    = Client::factory()->create(['phone' => '5551234567', 'rfc' => 'XAXX010101000']);
        $vehicle   = Vehicle::factory()->create(['client_id' => $client->id]);
        $recepcion = Recepcion::factory()->create(['client_id' => $client->id, 'vehicle_id' => $vehicle->id]);
        $order     = RepairOrder::factory()->create(['recepcion_id' => $recepcion->id, 'tax_rate' => 16]);

        RepairOrderItem::factory()->create([
            'repair_order_id' => $order->id, 'type' => 'labor',
            'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000,
        ]);
        RepairOrderItem::factory()->create([
            'repair_order_id' => $order->id, 'type' => 'part',
            'quantity' => 1, 'unit_price' => 500, 'subtotal' => 500,
        ]);

        return $order->load(['recepcion.client', 'recepcion.vehicle.brand', 'recepcion.vehicle.vehicleModel']);
    }

    private function datosTicket(Setting $settings): array
    {
        return [
            'settings'    => $settings,
            'order'       => new RepairOrder(['folio' => 'OT-1']),
            'payment'     => new Payment(['amount' => 100]),
            'cliente'     => 'Cliente de prueba',
            'vehiculo'    => 'Vehículo de prueba',
            'total'       => 100,
            'pagadoTotal' => 100,
            'saldo'       => 0,
            'metodo'      => 'Efectivo',
        ];
    }

    private function datosReporte(Setting $settings): array
    {
        return [
            'rows'     => collect(),
            'totals'   => ['labor_subtotal' => 0, 'parts_subtotal' => 0, 'tax' => 0, 'total' => 0],
            'month'    => 11,
            'year'     => 2026,
            'settings' => $settings,
            'lang'     => 'es',
        ];
    }

    public function test_plantillas_y_controladores_no_contienen_marcas_heredadas(): void
    {
        $archivos = array_merge(
            glob(resource_path('views/pdf/*.blade.php')),
            glob(app_path('Http/Controllers/*.php')),
        );
        $this->assertNotEmpty($archivos);

        foreach ($archivos as $archivo) {
            if (str_ends_with($archivo, 'SettingController.php')) {
                continue; // catálogo y textos de la propia pantalla de configuración
            }
            $contenido = file_get_contents($archivo);
            foreach (self::PROHIBIDOS as $marca) {
                $this->assertStringNotContainsString($marca, $contenido, basename($archivo) . " contiene [{$marca}]");
            }
        }
    }

    public function test_ticket_contiene_la_leyenda_completa_de_comprobante_interno(): void
    {
        $html = view('pdf.ticket_pago', $this->datosTicket($this->empresa()))->render();

        $this->assertStringContainsString(self::LEYENDA_TICKET, $html);
    }

    public function test_clausula_legal_de_recepcion_no_fue_modificada(): void
    {
        $plantilla = file_get_contents(resource_path('views/pdf/recepcion.blade.php'));

        $this->assertStringContainsString(self::CLAUSULA_LEGAL_ORIGINAL, $plantilla);
    }

    public function test_todas_las_plantillas_pdf_compilan(): void
    {
        foreach (glob(resource_path('views/pdf/*.blade.php')) as $archivo) {
            $compilada = app('blade.compiler')->compileString(file_get_contents($archivo));
            $this->assertNotEmpty($compilada, basename($archivo));
        }
    }

    public function test_los_cinco_documentos_se_generan_con_el_motor_pdf(): void
    {
        $settings = $this->empresa();
        $order    = $this->orden();
        $quote    = app(CreateQuoteAction::class)->execute($order);

        $documentos = [
            'recepcion' => ['pdf.recepcion', [
                'recepcion' => $order->recepcion,
                'settings'  => $settings,
                'client'    => $order->recepcion->client,
            ]],
            'cotizacion folio' => ['pdf.cotizacion-folio', [
                'quote'     => $quote->load('items'),
                'order'     => $order,
                'recepcion' => $order->recepcion,
                'settings'  => $settings,
            ]],
            'cotizacion clásica' => ['pdf.cotizacion', [
                'orden'     => $order,
                'recepcion' => $order->recepcion,
                'client'    => $order->recepcion->client,
                'vehicle'   => $order->recepcion->vehicle,
                'settings'  => $settings,
                'subtotal'  => 1500,
                'iva'       => 240,
                'tax_rate'  => 16,
                'total'     => 1740,
            ]],
            'ticket de pago' => ['pdf.ticket_pago', $this->datosTicket($settings)],
            'reporte de ingresos' => ['pdf.reporte_ingresos', $this->datosReporte($settings)],
        ];

        foreach ($documentos as $nombre => [$vista, $datos]) {
            $pdf = Pdf::loadView($vista, $datos)->output();
            $this->assertStringStartsWith('%PDF', $pdf, "El documento [{$nombre}] no produjo un PDF válido.");
        }
    }

    public function test_reporte_usa_el_logo_configurado_y_no_taller_png(): void
    {
        $settings = $this->empresa(['logo_path' => 'logos/test-logo-empresa.png']);
        $ruta     = storage_path('app/public/logos/test-logo-empresa.png');

        File::ensureDirectoryExists(dirname($ruta));
        File::put($ruta, 'logo de prueba');

        try {
            $html = view('pdf.reporte_ingresos', $this->datosReporte($settings))->render();

            // Los separadores dependen del sistema operativo (Windows usa \ y la plantilla puede mezclarlos).
            $rutaNormalizada = str_replace('\\', '/', $ruta);
            $htmlNormalizado = str_replace('\\', '/', $html);

            $this->assertStringContainsString($rutaNormalizada, $htmlNormalizado);
            $this->assertStringNotContainsString('Taller.png', $html);
        } finally {
            File::delete($ruta);
        }
    }

    public function test_ticket_oculta_los_campos_de_empresa_que_estan_vacios(): void
    {
        $settings = $this->empresa([
            'email' => null, 'phone' => null, 'address' => null, 'razon_social' => null, 'rfc' => null,
        ]);

        $html = view('pdf.ticket_pago', $this->datosTicket($settings))->render();

        $this->assertStringContainsString('Taller Real', $html);
        $this->assertStringNotContainsString('Tel.', $html);
        $this->assertStringNotContainsString('RFC:', $html);
    }

    public function test_los_cinco_documentos_con_logo_configurado(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put(
            'logos/documentos.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')
        );
        \App\Models\Setting::current()->update(['logo_path' => 'logos/documentos.png']);

        $this->test_los_cinco_documentos_se_generan_con_el_motor_pdf();
    }

    public function test_garantia_de_recepcion_no_imprime_directivas_literales(): void
    {
        $order    = $this->orden();
        $settings = $this->empresa();
        $settings->update([
            'warranty_service_enabled' => true,
            'warranty_service_days'    => 30,
            'warranty_service_km'      => 1000,
            'warranty_parts_enabled'   => true,
            'warranty_parts_days'      => 90,
        ]);

        $html = view('pdf.recepcion', [
            'recepcion' => $order->recepcion,
            'settings'  => $settings->fresh(),
            'client'    => $order->recepcion->client,
        ])->render();

        $this->assertStringNotContainsString('@if', $html);
        $this->assertStringNotContainsString('@endif', $html);
        $this->assertStringContainsString('1,000 km', $html);
    }
}
