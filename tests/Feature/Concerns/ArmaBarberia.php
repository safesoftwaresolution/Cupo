<?php

namespace Tests\Feature\Concerns;

use App\Models\Barberia;
use App\Models\Barbero;
use App\Models\Horario;
use App\Models\Servicio;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;

/**
 * Barbería de prueba: un barbero con horario de lunes 9:00-12:00 y 13:00-19:00,
 * dos servicios, y el reloj fijado el domingo 11 de octubre de 2026 a las 10:00.
 */
trait ArmaBarberia
{
    protected Barberia $barberia;

    protected Barbero $barbero;

    protected Servicio $corte;   // 30 min, $20.000

    protected Servicio $barba;   // 30 min, $15.000

    /** Lunes 12 de octubre de 2026 (ISO 1), el día que tiene horario. */
    protected Carbon $lunes;

    protected function armarBarberia(): void
    {
        Carbon::setTestNow('2026-10-11 10:00:00');
        $this->lunes = Carbon::parse('2026-10-12');

        $this->barberia = Barberia::factory()->create([
            'intervalo_agenda' => 15,
            'anticipacion_minima' => 60,
            'dias_reserva' => 30,
        ]);
        app(TenantContext::class)->definir($this->barberia);

        $this->barbero = Barbero::factory()->create(['barberia_id' => $this->barberia->id]);
        $this->corte = Servicio::factory()->create(['barberia_id' => $this->barberia->id, 'nombre' => 'Corte', 'duracion_minutos' => 30, 'precio' => 20000]);
        $this->barba = Servicio::factory()->create(['barberia_id' => $this->barberia->id, 'nombre' => 'Barba', 'duracion_minutos' => 30, 'precio' => 15000]);
        $this->barbero->servicios()->attach([$this->corte->id, $this->barba->id]);

        $this->horario($this->barbero, 1, '09:00', '12:00');
        $this->horario($this->barbero, 1, '13:00', '19:00');
    }

    protected function horario(Barbero $barbero, int $dia, string $inicio, string $fin): Horario
    {
        return app(TenantContext::class)->ejecutarEn($barbero->barberia_id, fn () => Horario::create([
            'barbero_id' => $barbero->id,
            'dia_semana' => $dia,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
