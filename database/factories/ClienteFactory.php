<?php

namespace Database\Factories;

use App\Models\Barberia;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'barberia_id' => Barberia::factory(),
            'nombre' => fake()->name(),
            'telefono' => '3'.fake()->unique()->numerify('#########'),
            'acepta_whatsapp' => true,
            'acepto_en' => now(),
        ];
    }
}
