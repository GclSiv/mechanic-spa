<?php

namespace Tests\Feature;

use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reconciliación de géneros antiguos (inglés) con los nombres en español.
 * Sin setUp de seed: cada prueba parte del estado que describe.
 */
class GenderCatalogReconcileTest extends TestCase
{
    use RefreshDatabase;

    private function mecanico(int $genderId, string $nombre): Mechanic
    {
        $tipo = MechanicType::firstOrCreate(['name' => 'General']);

        return Mechanic::create([
            'name' => $nombre,
            'gender_id' => $genderId,
            'mechanic_type_id' => $tipo->id,
        ]);
    }

    public function test_solo_male_se_renombra_conservando_el_id(): void
    {
        $male = Gender::create(['name' => 'Male']);
        $mec = $this->mecanico($male->id, 'Ref Male');

        $this->seed(CatalogSeeder::class);

        $this->assertSame('Masculino', $male->fresh()->name);
        $this->assertSame($male->id, $mec->fresh()->gender_id);
        $this->assertSame(0, Gender::where('name', 'Male')->count());
        $this->assertSame(1, Gender::where('name', 'Masculino')->count());
    }

    public function test_solo_female_se_renombra_conservando_el_id(): void
    {
        $female = Gender::create(['name' => 'Female']);
        $mec = $this->mecanico($female->id, 'Ref Female');

        $this->seed(CatalogSeeder::class);

        $this->assertSame('Femenino', $female->fresh()->name);
        $this->assertSame($female->id, $mec->fresh()->gender_id);
        $this->assertSame(0, Gender::where('name', 'Female')->count());
        $this->assertSame(1, Gender::where('name', 'Femenino')->count());
    }

    public function test_segunda_ejecucion_no_duplica_ni_cambia_ids(): void
    {
        Gender::create(['name' => 'Male']);
        Gender::create(['name' => 'Female']);

        $this->seed(CatalogSeeder::class);
        $antes = Gender::orderBy('id')->pluck('id', 'name')->all();

        $this->seed(CatalogSeeder::class);
        $despues = Gender::orderBy('id')->pluck('id', 'name')->all();

        $this->assertSame($antes, $despues);
        $this->assertSame(1, Gender::where('name', 'Masculino')->count());
        $this->assertSame(1, Gender::where('name', 'Femenino')->count());
        $this->assertSame(1, Gender::where('name', 'No especificar')->count());
    }

    public function test_coexistencia_antiguo_y_nuevo_se_reconcilia_sin_romper_fks(): void
    {
        $male = Gender::create(['name' => 'Male']);
        $masculino = Gender::create(['name' => 'Masculino']);
        $female = Gender::create(['name' => 'Female']);
        $femenino = Gender::create(['name' => 'Femenino']);

        $mecMale = $this->mecanico($male->id, 'Historico Male');
        $mecMasculino = $this->mecanico($masculino->id, 'Duplicado Masculino');
        $mecFemale = $this->mecanico($female->id, 'Historico Female');
        $mecFemenino = $this->mecanico($femenino->id, 'Duplicado Femenino');

        $this->seed(CatalogSeeder::class);

        // El ID histórico (inglés) es el canónico; el duplicado desaparece sin inventar IDs.
        $this->assertSame($male->id, Gender::where('name', 'Masculino')->value('id'));
        $this->assertSame($female->id, Gender::where('name', 'Femenino')->value('id'));
        $this->assertSame(1, Gender::where('name', 'Masculino')->count());
        $this->assertSame(1, Gender::where('name', 'Femenino')->count());
        $this->assertFalse(Gender::where('id', $masculino->id)->exists());
        $this->assertFalse(Gender::where('id', $femenino->id)->exists());

        $this->assertSame($male->id, $mecMale->fresh()->gender_id);
        $this->assertSame($male->id, $mecMasculino->fresh()->gender_id);
        $this->assertSame($female->id, $mecFemale->fresh()->gender_id);
        $this->assertSame($female->id, $mecFemenino->fresh()->gender_id);
    }
}
