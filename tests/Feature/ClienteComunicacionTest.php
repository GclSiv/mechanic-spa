<?php

namespace Tests\Feature;

use App\Actions\Quotes\CreateQuoteAction;
use App\Models\Client;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\Quote;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Bloque "Cliente / seguimiento" y props de comunicación: solo owner/admin reciben la URL pública,
 * el estado derivado de autorización y la cotización actual. El mecánico no recibe nada de eso.
 */
class ClienteComunicacionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** @return array{0: User, 1: Mechanic} */
    private function mecanico(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);
        $mechanic = Mechanic::create([
            'name'             => 'Mecánico de prueba',
            'user_id'          => $user->id,
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        return [$user, $mechanic];
    }

    private function orden(?Mechanic $mechanic = null): RepairOrder
    {
        $client = Client::factory()->create([
            'first_name' => 'Ana', 'last_name' => 'Ruiz', 'phone' => '5551234567', 'rfc' => 'XAXX010101000',
        ]);
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $recepcion = Recepcion::factory()->create(['client_id' => $client->id, 'vehicle_id' => $vehicle->id]);

        $order = RepairOrder::factory()->create([
            'recepcion_id' => $recepcion->id, 'tax_rate' => 16, 'mechanic_id' => $mechanic?->id,
        ]);

        RepairOrderItem::factory()->create([
            'repair_order_id' => $order->id, 'type' => 'labor',
            'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000,
        ]);

        return $order;
    }

    private function mostrar(User $usuario, RepairOrder $order)
    {
        return $this->actingAs($usuario)->get(route('repair-orders.show', $order));
    }

    public function test_admin_recibe_tracking_url_derivada_del_backend_y_no_el_token_de_la_orden(): void
    {
        $order = $this->orden();

        $this->mostrar($this->admin(), $order)->assertInertia(fn (Assert $page) => $page
            ->where('comunicacion.tracking_url', route('tracking.show', ['token' => $order->tracking_token]))
            ->where('comunicacion.cliente_nombre', 'Ana Ruiz')
            ->where('comunicacion.autorizacion', 'sin_pendiente')
            ->where('comunicacion.cotizacion_actual', null)
            ->where('comunicacion.total_cotizaciones', 0)
            ->missing('orden.tracking_token'));
    }

    public function test_mecanico_no_recibe_enlace_publico_telefono_cotizaciones_ni_comunicacion(): void
    {
        [$user, $mechanic] = $this->mecanico();
        $order = $this->orden($mechanic);
        app(CreateQuoteAction::class)->execute($order);

        $this->mostrar($user, $order)->assertInertia(fn (Assert $page) => $page
            ->where('isAdmin', false)
            ->where('comunicacion', null)
            ->missing('orden.tracking_token')
            ->missing('orden.quotes')
            ->missing('orden.recepcion.client.phone'));
    }

    public function test_sin_cotizacion_no_dice_que_hay_algo_pendiente(): void
    {
        $order = $this->orden();

        $this->mostrar($this->admin(), $order)->assertInertia(fn (Assert $page) => $page
            ->where('comunicacion.autorizacion', 'sin_pendiente')
            ->where('comunicacion.cotizacion_actual', null));
    }

    public function test_borrador_no_se_presenta_como_autorizacion_pendiente(): void
    {
        $order = $this->orden();
        app(CreateQuoteAction::class)->execute($order); // queda en borrador

        $this->mostrar($this->admin(), $order)->assertInertia(fn (Assert $page) => $page
            ->where('comunicacion.autorizacion', 'sin_pendiente')
            ->where('comunicacion.cotizacion_actual.status', Quote::BORRADOR)
            ->where('comunicacion.total_cotizaciones', 1));
    }

    public function test_cotizacion_enviada_queda_esperando_autorizacion(): void
    {
        $order = $this->orden();
        $quote = app(CreateQuoteAction::class)->execute($order);
        $quote->markSent();

        $this->mostrar($this->admin(), $order)->assertInertia(fn (Assert $page) => $page
            ->where('comunicacion.autorizacion', 'esperando_autorizacion')
            ->where('comunicacion.cotizacion_actual.id', $quote->id)
            ->where('comunicacion.cotizacion_actual.status', Quote::ENVIADA)
            ->where('comunicacion.cotizacion_actual.total', 1160));
    }

    public function test_cotizacion_aprobada_por_cliente_queda_autorizada(): void
    {
        $order = $this->orden();
        $quote = app(CreateQuoteAction::class)->execute($order);
        $quote->markSent();
        $quote->approve('enlace', 'Ana Ruiz');

        $this->mostrar($this->admin(), $order)->assertInertia(fn (Assert $page) => $page
            ->where('comunicacion.autorizacion', 'autorizada')
            ->where('comunicacion.cotizacion_actual.status', Quote::APROBADA)
            ->where('comunicacion.cotizacion_actual.aprobada_por', 'Ana Ruiz'));
    }

    public function test_cotizacion_rechazada_muestra_rechazo_y_motivo_sin_borrar_historial(): void
    {
        $order = $this->orden();
        $quote = app(CreateQuoteAction::class)->execute($order);
        $quote->markSent();
        $quote->reject('Es muy caro');

        $this->mostrar($this->admin(), $order)->assertInertia(fn (Assert $page) => $page
            ->where('comunicacion.autorizacion', 'rechazada')
            ->where('comunicacion.cotizacion_actual.status', Quote::RECHAZADA)
            ->where('comunicacion.cotizacion_actual.rejection_reason', 'Es muy caro')
            ->where('comunicacion.total_cotizaciones', 1));
        $this->assertSame(Quote::RECHAZADA, $quote->fresh()->status);
    }

    public function test_una_nueva_enviada_tiene_prioridad_sobre_una_autorizacion_previa(): void
    {
        $order = $this->orden();
        $action = app(CreateQuoteAction::class);

        $principal = $action->execute($order);
        $principal->markSent();
        $principal->approve('presencial', 'Asesor');

        RepairOrderItem::factory()->create([
            'repair_order_id' => $order->id, 'type' => 'part',
            'quantity' => 1, 'unit_price' => 500, 'subtotal' => 500,
        ]); // concepto nuevo, sin autorizar, para la adicional

        $adicional = $action->execute($order, null, Quote::KIND_ADICIONAL);
        $adicional->markSent();

        $this->mostrar($this->admin(), $order)->assertInertia(fn (Assert $page) => $page
            ->where('comunicacion.autorizacion', 'esperando_autorizacion')
            ->where('comunicacion.cotizacion_actual.id', $adicional->id)
            ->where('comunicacion.total_cotizaciones', 2));
    }

    public function test_el_estado_de_autorizacion_no_se_persiste_en_la_orden(): void
    {
        $order = $this->orden();
        $quote = app(CreateQuoteAction::class)->execute($order);
        $quote->markSent();
        $statusAntes = $order->fresh()->status_id;

        $this->mostrar($this->admin(), $order)->assertOk();

        $this->assertSame($statusAntes, $order->fresh()->status_id);
        $this->assertFalse(array_key_exists('autorizacion', $order->fresh()->getAttributes()));
    }

    /** Cotización adicional con su propio concepto sin autorizar (las adicionales no reutilizan conceptos facturados). */
    private function adicional(RepairOrder $order, int $precio): Quote
    {
        RepairOrderItem::factory()->create([
            'repair_order_id' => $order->id, 'type' => 'part',
            'quantity' => 1, 'unit_price' => $precio, 'subtotal' => $precio,
        ]);

        return app(CreateQuoteAction::class)->execute($order, null, Quote::KIND_ADICIONAL);
    }

    private function principalAprobada(RepairOrder $order): Quote
    {
        $principal = app(CreateQuoteAction::class)->execute($order);
        $principal->markSent();
        $principal->approve('presencial', 'Asesor');

        return $principal;
    }

    public function test_principal_aprobada_mas_adicional_enviada_espera_autorizacion(): void
    {
        $order = $this->orden();
        $this->principalAprobada($order);
        $adicional = $this->adicional($order, 500);
        $adicional->markSent();

        $this->mostrar($this->admin(), $order)->assertInertia(fn (Assert $page) => $page
            ->where('comunicacion.autorizacion', 'esperando_autorizacion')
            ->where('comunicacion.adicional_rechazado', false)
            ->where('comunicacion.cotizacion_actual.id', $adicional->id)
            ->where('comunicacion.cotizacion_actual.status', Quote::ENVIADA));
    }

    public function test_principal_aprobada_mas_adicional_rechazada_indica_autorizada_con_adicional_rechazado(): void
    {
        $order = $this->orden();
        $principal = $this->principalAprobada($order);
        $adicional = $this->adicional($order, 500);
        $adicional->markSent();
        $adicional->reject('Es mucho');

        $this->mostrar($this->admin(), $order)->assertInertia(fn (Assert $page) => $page
            ->where('comunicacion.autorizacion', 'autorizada')
            ->where('comunicacion.adicional_rechazado', true)
            ->where('comunicacion.cotizacion_actual.id', $principal->id)
            ->where('comunicacion.cotizacion_actual.status', Quote::APROBADA));
    }

    public function test_principal_aprobada_adicional_aprobada_y_otra_adicional_rechazada_mantiene_la_nota(): void
    {
        $order = $this->orden();
        $principal = $this->principalAprobada($order);

        $aprobada = $this->adicional($order, 300);
        $aprobada->markSent();
        $aprobada->approve('telefono', 'Asesor');

        $rechazada = $this->adicional($order, 400);
        $rechazada->markSent();
        $rechazada->reject('No la necesito');

        $this->mostrar($this->admin(), $order)->assertInertia(fn (Assert $page) => $page
            ->where('comunicacion.autorizacion', 'autorizada')
            ->where('comunicacion.adicional_rechazado', true)
            ->where('comunicacion.total_cotizaciones', 3)
            ->where('comunicacion.cotizacion_actual.id', $aprobada->id)
            ->where('comunicacion.cotizacion_actual.status', Quote::APROBADA));
        $this->assertSame(Quote::APROBADA, $principal->fresh()->status);
    }

    public function test_rechazada_sin_ninguna_aprobada_queda_rechazada_sin_nota_de_adicional(): void
    {
        $order = $this->orden();
        $principal = app(CreateQuoteAction::class)->execute($order);
        $principal->markSent();
        $principal->reject('Muy caro');

        $adicional = $this->adicional($order, 400);
        $adicional->markSent();
        $adicional->reject('No la necesito');

        $this->mostrar($this->admin(), $order)->assertInertia(fn (Assert $page) => $page
            ->where('comunicacion.autorizacion', 'rechazada')
            ->where('comunicacion.adicional_rechazado', false)
            ->where('comunicacion.cotizacion_actual.status', Quote::RECHAZADA));
    }
}
