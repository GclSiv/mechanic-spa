<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClientPhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => \App\Models\Client::factory(), // Crea un cliente si no hay uno
            'path' => 'vehiculos/demo.jpg', 
            'label' => $this->faker->randomElement(['Frente', 'Trasera', 'Interior', 'Motor']),
        ];
    }
}