<?php

namespace Tests\Feature;

use App\Actions\Clients\ImportClientsAction;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClientImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'clients');
        file_put_contents($path, $content);

        return $path;
    }

    public function test_imports_valid_rows_and_reports_duplicates_and_errors(): void
    {
        Client::factory()->create(['phone' => '5550000000']);

        $path = $this->csv(
            "nombre;apellidos;telefono;direccion;rfc;correo;pais\n"
            . "Ana;López;5551111111;Centro;XAXX010101000;ana@correo.com;MX\n"
            . "Luis;Ramos;5550000000;Otra;;;US\n"               // duplicado por teléfono
            . ";Sin nombre;5552222222;;;;MX\n"                   // falta nombre
            . "Eva;Cruz;5553333333;;;correo-malo;MX\n"           // correo inválido
        );

        $result = app(ImportClientsAction::class)->execute($path);

        $this->assertSame(1, $result['creados']);
        $this->assertCount(1, $result['omitidos']);
        $this->assertCount(2, $result['errores']);
        $this->assertDatabaseHas('clients', ['phone' => '5551111111', 'rfc' => 'XAXX010101000']);
    }

    public function test_accepts_comma_separated_files_and_defaults_country_to_mx(): void
    {
        $path = $this->csv("nombre,apellidos,telefono\nMarta,Díaz,5554444444\n");

        $result = app(ImportClientsAction::class)->execute($path);

        $this->assertSame(1, $result['creados']);
        $this->assertDatabaseHas('clients', ['first_name' => 'Marta', 'country' => 'MX']);
    }

    public function test_rejects_file_without_required_columns(): void
    {
        $path = $this->csv("nombre;telefono\nJuan;5551234567\n");

        $this->expectException(ValidationException::class);
        app(ImportClientsAction::class)->execute($path);
    }

    public function test_mechanic_cannot_import_clients(): void
    {
        $mechanic = User::factory()->create(['role' => 'mechanic']);

        $this->actingAs($mechanic)
            ->post(route('clients.import.store'), ['file' => UploadedFile::fake()->create('c.csv', 1, 'text/csv')])
            ->assertForbidden();

        $this->assertDatabaseCount('clients', 0);
    }
}
