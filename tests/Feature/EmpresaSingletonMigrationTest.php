<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Migración singleton de settings: reversible, conserva la fila existente y no migra con varias filas.
 */
class EmpresaSingletonMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_21_000002_add_singleton_key_to_settings_table.php');
    }

    public function test_up_down_up_es_reversible_y_asigna_default_a_la_fila_existente(): void
    {
        $migracion = $this->migracion();

        $migracion->down();
        $this->assertFalse(Schema::hasColumn('settings', 'singleton_key'));

        DB::table('settings')->insert(['company_name' => 'Fila previa', 'tax_type' => 'MX', 'iva' => 16.00]);

        $migracion->up();
        $this->assertTrue(Schema::hasColumn('settings', 'singleton_key'));
        $this->assertSame('default', DB::table('settings')->value('singleton_key'));
        $this->assertSame('Fila previa', Setting::current()->company_name);

        $migracion->down();
        $this->assertFalse(Schema::hasColumn('settings', 'singleton_key'));

        $migracion->up();
        $this->assertTrue(Schema::hasColumn('settings', 'singleton_key'));
    }

    public function test_no_migra_con_varias_filas_y_no_borra_nada(): void
    {
        $migracion = $this->migracion();
        $migracion->down();

        DB::table('settings')->insert([
            ['company_name' => 'Histórica A', 'tax_type' => 'MX', 'iva' => 16.00],
            ['company_name' => 'Histórica B', 'tax_type' => 'MX', 'iva' => 8.00],
        ]);

        try {
            $migracion->up();
            $this->fail('La migración debió rechazar varias filas');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('2 filas', $e->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('settings', 'singleton_key'));
        $this->assertSame(2, DB::table('settings')->count());
    }
}
