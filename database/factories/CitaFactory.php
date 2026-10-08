<?php

namespace Database\Factories;

use App\Enums\EstadoCita;
use App\Enums\OrigenCita;
use App\Models\Barberia;
use App\Models\Barbero;
use App\Models\Cita;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cita>
 */
class CitaFactory extends Factory
{
    public function definition(): array
    {
        $inicio = now()->addDay()->setTime(fake()->numberBetween(9, 17), fake()->randomElement([0, 30]));
        $duracion = 30;

        return [
            'codigo' => Str::lower(Str::random(10)),
            'barberia_id' => Barberia::factory(),
            // Barbero y cliente nacen en la misma barbería que la cita.
            'barbero_id' => fn (array $attrs) => Barbero::factory()->create(['barberia_id' => $attrs['barberia_id']])->id,
            'cliente_id' => fn (array $attrs) => Cliente::factory()->create(['barberia_id' => $attrs['barberia_id']])->id,
            'inicia_en' => $inicio,
            'termina_en' => $inicio->copy()->addMinutes($duracion),
            'estado' => EstadoCita::Pendiente,
            'origen' => OrigenCita::Manual,
            'precio_total' => 25000,
            'duracion_total_minutos' => $duracion,
        ];
    }

    public function estado(EstadoCita $estado): static
    {
        return $this->state(fn () => ['estado' => $estado]);
    }
}
