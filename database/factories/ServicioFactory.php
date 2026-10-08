<?php

namespace Database\Factories;

use App\Models\Barberia;
use App\Models\Servicio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Servicio>
 */
class ServicioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'barberia_id' => Barberia::factory(),
            'nombre' => fake()->randomElement(['Corte clásico', 'Fade', 'Barba', 'Corte y barba', 'Cejas']),
            'duracion_minutos' => fake()->randomElement([20, 30, 45, 60]),
            'precio' => fake()->randomElement([15000, 20000, 25000, 35000, 45000]),
            'orden' => 0,
            'activo' => true,
        ];
    }
}
