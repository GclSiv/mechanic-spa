<?php

namespace Database\Factories;

use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Mecánico válido: name, gender_id y mechanic_type_id son NOT NULL en la tabla.
 * commission_rate tiene default 0 en la base; no se fija aquí.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mechanic>
 */
class MechanicFactory extends Factory
{
    protected $model = Mechanic::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'gender_id' => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
            'phone' => null,
        ];
    }
}
