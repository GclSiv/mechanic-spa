<?php

namespace Tests\Feature;

use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\RepairOrder;
use App\Models\RepairOrderPhoto;
use App\Models\RepairOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AvancePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public static function estadosConFotos(): array
    {
        return [
            'diagnostico'  => ['diagnostico'],
            'en-reparacion' => ['en-reparacion'],
        ];
    }

    public static function estadosCerrados(): array
    {
        return [
            'entregado' => ['entregado'],
            'rechazado' => ['rechazado'],
        ];
    }

    private function ordenEn(string $slug, ?int $mechanicId = null): RepairOrder
    {
        $status = RepairOrderStatus::firstOrCreate(['slug' => $slug], ['name' => $slug, 'color_class' => 'bg-gray-500']);

        return RepairOrder::factory()->create(['status_id' => $status->id, 'mechanic_id' => $mechanicId]);
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

    private function fotoEn(RepairOrder $order): RepairOrderPhoto
    {
        $path = 'avance/' . $order->id . '/prueba.jpg';
        Storage::disk('public')->put($path, 'x');

        return RepairOrderPhoto::create(['repair_order_id' => $order->id, 'path' => $path]);
    }

    private function imagen(string $nombre = 'avance.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nombre, 400, 300);
    }

    #[DataProvider('estadosConFotos')]
    public function test_admin_sube_foto_en_orden_abierta(string $slug): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->ordenEn($slug);

        $this->actingAs($admin)
            ->post(route('repair-orders.avance.store', $order), ['photo' => $this->imagen(), 'note' => 'Bomba instalada'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('repair_order_photos', ['repair_order_id' => $order->id, 'note' => 'Bomba instalada']);
        $this->assertCount(1, Storage::disk('public')->allFiles('avance/' . $order->id));
    }

    #[DataProvider('estadosConFotos')]
    public function test_mecanico_asignado_sube_foto_en_orden_abierta(string $slug): void
    {
        [$user, $mechanic] = $this->mecanico();
        $order = $this->ordenEn($slug, $mechanic->id);

        $this->actingAs($user)
            ->post(route('repair-orders.avance.store', $order), ['photo' => $this->imagen()])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('repair_order_photos', 1);
    }

    public function test_admin_elimina_foto_en_orden_abierta(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->ordenEn('diagnostico');
        $foto = $this->fotoEn($order);

        $this->actingAs($admin)
            ->delete(route('repair-orders.avance.destroy', [$order, $foto]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('repair_order_photos', ['id' => $foto->id]);
        Storage::disk('public')->assertMissing($foto->path);
    }

    #[DataProvider('estadosCerrados')]
    public function test_nadie_sube_fotos_en_orden_cerrada(string $slug): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$mecanico, $mechanic] = $this->mecanico();
        $order = $this->ordenEn($slug, $mechanic->id);

        $this->actingAs($admin)
            ->post(route('repair-orders.avance.store', $order), ['photo' => $this->imagen()])
            ->assertForbidden();

        $this->actingAs($mecanico)
            ->post(route('repair-orders.avance.store', $order), ['photo' => $this->imagen()])
            ->assertForbidden();

        $this->assertDatabaseCount('repair_order_photos', 0);
        $this->assertCount(0, Storage::disk('public')->allFiles('avance/' . $order->id));
    }

    #[DataProvider('estadosCerrados')]
    public function test_nadie_elimina_fotos_en_orden_cerrada(string $slug): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$mecanico, $mechanic] = $this->mecanico();
        $order = $this->ordenEn($slug, $mechanic->id);
        $foto = $this->fotoEn($order);

        $this->actingAs($admin)
            ->delete(route('repair-orders.avance.destroy', [$order, $foto]))
            ->assertForbidden();

        $this->actingAs($mecanico)
            ->delete(route('repair-orders.avance.destroy', [$order, $foto]))
            ->assertForbidden();

        $this->assertDatabaseHas('repair_order_photos', ['id' => $foto->id]);
        Storage::disk('public')->assertExists($foto->path);
    }

    public function test_rechaza_archivo_que_no_es_imagen(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->ordenEn('diagnostico');

        $this->actingAs($admin)
            ->post(route('repair-orders.avance.store', $order), [
                'photo' => UploadedFile::fake()->create('documento.txt', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('repair_order_photos', 0);
    }

    public function test_rechaza_imagen_mayor_a_5_mb(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->ordenEn('diagnostico');

        $this->actingAs($admin)
            ->post(route('repair-orders.avance.store', $order), [
                'photo' => UploadedFile::fake()->image('grande.jpg', 400, 300)->size(5121),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('repair_order_photos', 0);
    }

    public function test_la_ruta_interna_no_se_serializa_a_la_vista(): void
    {
        $order = $this->ordenEn('diagnostico');
        $foto = $this->fotoEn($order);

        $this->assertArrayNotHasKey('path', $foto->fresh()->toArray());
        $this->assertArrayHasKey('id', $foto->fresh()->toArray());
        $this->assertArrayHasKey('note', $foto->fresh()->toArray());
    }
}
