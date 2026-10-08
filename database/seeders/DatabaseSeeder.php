<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Barberia;
use App\Models\Barbero;
use App\Models\Cliente;
use App\Models\Horario;
use App\Models\Servicio;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Crea una barbería de demostración completa. Solo para desarrollo local.
     */
    public function run(): void
    {
        $barberia = Barberia::factory()->create([
            'nombre' => 'Barbería Demo',
            'slug' => 'demo',
            'ciudad' => 'Bogotá',
        ]);

        User::factory()->create([
            'barberia_id' => $barberia->id,
            'rol' => Rol::Dueno,
            'name' => 'Dueño Demo',
            'email' => 'demo@cupo.test',
        ]);

        app(TenantContext::class)->ejecutarEn($barberia, function () use ($barberia) {
            $servicios = collect([
                ['Corte clásico', 30, 20000],
                ['Fade', 45, 25000],
                ['Barba', 30, 15000],
                ['Corte y barba', 60, 35000],
            ])->map(fn ($s, $i) => Servicio::create([
                'nombre' => $s[0],
                'duracion_minutos' => $s[1],
                'precio' => $s[2],
                'orden' => $i,
            ]));

            Barbero::factory()->count(3)->create(['barberia_id' => $barberia->id])
                ->each(function (Barbero $barbero) use ($servicios) {
                    $barbero->servicios()->attach($servicios->pluck('id'));

                    // Lunes a sábado, con almuerzo de 12:00 a 13:00.
                    foreach (range(1, 6) as $dia) {
                        Horario::create(['barbero_id' => $barbero->id, 'dia_semana' => $dia, 'hora_inicio' => '09:00', 'hora_fin' => '12:00']);
                        Horario::create(['barbero_id' => $barbero->id, 'dia_semana' => $dia, 'hora_inicio' => '13:00', 'hora_fin' => '19:00']);
                    }
                });

            Cliente::factory()->count(15)->create(['barberia_id' => $barberia->id]);
        });
    }
}
