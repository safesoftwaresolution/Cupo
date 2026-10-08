<?php

namespace Database\Factories;

use App\Models\Barberia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Barberia>
 */
class BarberiaFactory extends Factory
{
    public function definition(): array
    {
        $nombre = 'Barbería '.fake()->unique()->lastName();

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre).'-'.fake()->unique()->numerify('###'),
            'telefono' => '573'.fake()->numerify('#########'),
            'whatsapp_numero' => '573'.fake()->numerify('#########'),
            'direccion' => fake()->streetAddress(),
            'ciudad' => fake()->randomElement(['Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Bucaramanga']),
            'plan' => 'basico',
            'prueba_hasta' => now()->addDays(60),
            'intervalo_agenda' => 15,
            'activa' => true,
        ];
    }
}
