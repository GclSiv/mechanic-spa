<?php

namespace Database\Factories;

use App\Models\ClientStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name'       => $this->faker->firstName(),
            'last_name'        => $this->faker->lastName(),
            'phone'            => $this->faker->numerify('##########'),
            'address'          => $this->faker->streetAddress(),
            'rfc'              => null,
            'country'          => 'MX',
            'client_status_id' => ClientStatus::firstOrCreate(
                ['name' => 'Active'],
                ['color' => 'bg-green-500']
            )->id,
        ];
    }
}
