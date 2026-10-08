<?php

namespace Tests\Feature;

use App\Enums\EstadoCita;
use App\Models\Barbero;
use App\Models\Bloqueo;
use App\Models\Cita;
use App\Support\Disponibilidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\Feature\Concerns\ArmaBarberia;
use Tests\TestCase;

class DisponibilidadTest extends TestCase
{
    use ArmaBarberia, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->armarBarberia();
    }

    /**
     * @return list<string>
     */
    private function horas(int $duracion = 30, ?Carbon $fecha = null, ?Carbon $desde = null, ?Barbero $barbero = null): array
    {
        return app(Disponibilidad::class)
            ->huecos($barbero ?? $this->barbero, $fecha ?? $this->lunes, $duracion, $desde)
            ->map(fn (Carbon $h) => $h->format('H:i'))
            ->all();
    }

    private function cita(string $inicio, string $fin, EstadoCita $estado = EstadoCita::Confirmada): Cita
    {
        return Cita::factory()->estado($estado)->create([
            'barberia_id' => $this->barberia->id,
            'barbero_id' => $this->barbero->id,
            'inicia_en' => Carbon::parse("2026-10-12 $inicio"),
            'termina_en' => Carbon::parse("2026-10-12 $fin"),
        ]);
    }

    public function test_genera_horas_cada_intervalo_dentro_de_cada_franja(): void
    {
        $horas = $this->horas(30);

        $this->assertSame('09:00', $horas[0]);
        $this->assertContains('11:30', $horas);
        $this->assertNotContains('11:45', $horas, 'Una cita de 30 min a las 11:45 terminaría a las 12:15, fuera de la franja.');
        $this->assertNotContains('12:00', $horas, 'La hora del almuerzo no se ofrece.');
        $this->assertNotContains('12:30', $horas);
        $this->assertContains('13:00', $horas);
        $this->assertSame('18:30', end($horas));
    }

    public function test_los_servicios_largos_dejan_menos_horas(): void
    {
        $horas = $this->horas(45);

        $this->assertContains('11:15', $horas);
        $this->assertNotContains('11:30', $horas);
        $this->assertSame('18:15', end($horas));
    }

    public function test_una_cita_bloquea_todas_las_horas_que_se_le_cruzan(): void
    {
        $this->cita('10:00', '10:30');

        $horas = $this->horas(30);

        foreach (['09:45', '10:00', '10:15'] as $ocupada) {
            $this->assertNotContains($ocupada, $horas);
        }
        $this->assertContains('09:30', $horas);
        $this->assertContains('10:30', $horas, 'Una cita que empieza justo cuando otra termina es válida.');
    }

    public function test_una_cita_cancelada_o_con_inasistencia_libera_el_cupo(): void
    {
        $this->cita('10:00', '10:30', EstadoCita::Cancelada);
        $this->cita('11:00', '11:30', EstadoCita::NoAsistio);

        $horas = $this->horas(30);

        $this->assertContains('10:00', $horas);
        $this->assertContains('11:00', $horas);
    }

    public function test_los_bloqueos_quitan_las_horas_del_rango(): void
    {
        Bloqueo::create([
            'barbero_id' => $this->barbero->id,
            'inicia_en' => Carbon::parse('2026-10-12 14:00'),
            'termina_en' => Carbon::parse('2026-10-12 16:00'),
            'motivo' => 'Cita médica',
        ]);

        $horas = $this->horas(30);

        $this->assertContains('13:30', $horas);
        $this->assertNotContains('14:00', $horas);
        $this->assertNotContains('15:45', $horas);
        $this->assertContains('16:00', $horas);
    }

    public function test_un_dia_sin_horario_no_tiene_horas(): void
    {
        $this->assertSame([], $this->horas(30, Carbon::parse('2026-10-13'))); // martes
    }

    public function test_aplica_la_anticipacion_minima_de_la_barberia(): void
    {
        Carbon::setTestNow('2026-10-12 09:30:00');

        $horas = $this->horas(30);

        $this->assertNotContains('10:15', $horas);
        $this->assertSame('10:30', $horas[0], 'Con 60 minutos de anticipación, lo más pronto es las 10:30.');
    }

    public function test_el_personal_puede_agendar_desde_el_inicio_del_dia(): void
    {
        Carbon::setTestNow('2026-10-12 09:30:00');

        $horas = $this->horas(30, desde: Carbon::parse('2026-10-12 00:00'));

        $this->assertSame('09:00', $horas[0]);
    }

    public function test_no_se_ofrecen_dias_mas_alla_del_limite_de_reserva(): void
    {
        $this->barberia->update(['dias_reserva' => 7]);
        $this->barbero->refresh();

        $this->assertSame([], $this->horas(30, Carbon::parse('2026-10-19')->addDays(7)), 'Un lunes lejano queda fuera.');
        $this->assertNotEmpty($this->horas(30, Carbon::parse('2026-10-12')));
    }

    public function test_respeta_el_intervalo_configurado_de_la_agenda(): void
    {
        $this->barberia->update(['intervalo_agenda' => 30]);
        $this->barbero->refresh();

        $horas = $this->horas(30);

        $this->assertContains('09:30', $horas);
        $this->assertNotContains('09:15', $horas);
    }

    public function test_solo_ofrece_barberos_que_hacen_todos_los_servicios(): void
    {
        $soloCortes = Barbero::factory()->create(['barberia_id' => $this->barberia->id, 'nombre' => 'Solo cortes']);
        $soloCortes->servicios()->attach($this->corte->id);
        $this->horario($soloCortes, 1, '09:00', '12:00');

        $disponibilidad = app(Disponibilidad::class);

        $conAmbos = $disponibilidad->paraServicios([$this->corte->id, $this->barba->id], null, $this->lunes);
        $this->assertSame([$this->barbero->id], $conAmbos['barberos']->map(fn ($x) => $x['barbero']->id)->all());
        $this->assertSame(60, $conAmbos['duracion']);

        $soloUno = $disponibilidad->paraServicios([$this->corte->id], null, $this->lunes);
        $this->assertCount(2, $soloUno['barberos']);
    }

    public function test_un_servicio_oculto_o_inexistente_no_ofrece_nada(): void
    {
        $this->barba->update(['activo' => false]);

        $resultado = app(Disponibilidad::class)->paraServicios([$this->corte->id, $this->barba->id], null, $this->lunes);
        $this->assertTrue($resultado['barberos']->isEmpty());

        $inexistente = app(Disponibilidad::class)->paraServicios([9999], null, $this->lunes);
        $this->assertInstanceOf(Collection::class, $inexistente['barberos']);
        $this->assertTrue($inexistente['barberos']->isEmpty());
    }

    public function test_un_barbero_inactivo_no_aparece(): void
    {
        $this->barbero->update(['activo' => false]);

        $resultado = app(Disponibilidad::class)->paraServicios([$this->corte->id], null, $this->lunes);

        $this->assertTrue($resultado['barberos']->isEmpty());
    }
}
