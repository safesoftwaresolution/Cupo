<?php

namespace Database\Factories;

use App\Models\Barberia;
use App\Models\Barbero;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barbero>
 */
class BarberoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'barberia_id' => Barberia::factory(),
            'nombre' => fake()->firstNameMale().' '.fake()->lastName(),
            'telefono' => '573'.fake()->numerify('#########'),
            'comision_porcentaje' => fake()->randomElement([40, 50, 60]),
            'color' => fake()->hexColor(),
            'activo' => true,
        ];
    }
}
