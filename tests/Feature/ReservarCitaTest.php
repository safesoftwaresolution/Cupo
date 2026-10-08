<?php

namespace Tests\Feature;

use App\Actions\ReservarCita;
use App\Enums\EstadoCita;
use App\Enums\OrigenCita;
use App\Exceptions\CupoNoDisponible;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\ArmaBarberia;
use Tests\TestCase;

class ReservarCitaTest extends TestCase
{
    use ArmaBarberia, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->armarBarberia();
    }

    private function reservar(string $hora, ?array $servicios = null, array $cliente = [], ?Carbon $desde = null): Cita
    {
        return app(ReservarCita::class)(
            $this->barbero,
            $servicios ?? [$this->corte->id],
            Carbon::parse("2026-10-12 $hora"),
            [...['nombre' => 'Juan Pérez', 'telefono' => '300 123 4567', 'acepta_whatsapp' => true], ...$cliente],
            OrigenCita::Web,
            $desde,
        );
    }

    public function test_crea_la_cita_con_fin_precio_y_copia_de_los_servicios(): void
    {
        $this->barbero->servicios()->updateExistingPivot($this->barba->id, ['precio_especial' => 12000]);

        $cita = $this->reservar('10:00', [$this->corte->id, $this->barba->id]);

        $this->assertSame('2026-10-12 10:00:00', $cita->inicia_en->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-12 11:00:00', $cita->termina_en->format('Y-m-d H:i:s'));
        $this->assertSame(32000, $cita->precio_total, 'Corte $20.000 + barba con precio especial $12.000');
        $this->assertSame(60, $cita->duracion_total_minutos);
        $this->assertSame(EstadoCita::Pendiente, $cita->estado);
        $this->assertSame(OrigenCita::Web, $cita->origen);
        $this->assertSame(10, strlen($cita->codigo));
        $this->assertSame($this->barberia->id, $cita->barberia_id);

        $copias = $cita->servicios()->get()->mapWithKeys(fn ($s) => [$s->pivot->nombre => $s->pivot->precio]);
        $this->assertSame(['Corte' => 20000, 'Barba' => 12000], $copias->all());
    }

    public function test_la_copia_del_servicio_no_cambia_si_luego_cambia_el_precio(): void
    {
        $cita = $this->reservar('10:00');

        $this->corte->update(['precio' => 99000, 'nombre' => 'Corte premium']);

        $copia = $cita->servicios()->first()->pivot;
        $this->assertSame('Corte', $copia->nombre);
        $this->assertSame(20000, $copia->precio);
    }

    public function test_crea_al_cliente_y_registra_su_autorizacion(): void
    {
        $cita = $this->reservar('10:00');

        $cliente = $cita->cliente;
        $this->assertSame('573001234567', $cliente->telefono);
        $this->assertSame('Juan Pérez', $cliente->nombre);
        $this->assertTrue($cliente->acepta_whatsapp);
        $this->assertNotNull($cliente->acepto_en);
    }

    public function test_el_mismo_celular_es_el_mismo_cliente(): void
    {
        $this->reservar('10:00', cliente: ['telefono' => '3001234567']);
        $this->reservar('11:00', cliente: ['telefono' => '+57 300-123-4567', 'nombre' => 'Otro nombre']);

        $this->assertSame(1, Cliente::count());
        $this->assertSame('Juan Pérez', Cliente::first()->nombre, 'No se pisa el nombre de un cliente conocido.');
        $this->assertSame(2, Cita::count());
    }

    public function test_reservar_sin_autorizar_no_otorga_consentimiento(): void
    {
        $cita = $this->reservar('10:00', cliente: ['acepta_whatsapp' => false]);

        $this->assertFalse($cita->cliente->acepta_whatsapp);
        $this->assertNull($cita->cliente->acepto_en);
    }

    public function test_no_permite_reservar_el_mismo_horario_dos_veces(): void
    {
        $this->reservar('10:00');

        $this->expectException(CupoNoDisponible::class);
        $this->reservar('10:00', cliente: ['telefono' => '3109998877']);
    }

    public function test_no_permite_una_cita_que_se_cruza_parcialmente(): void
    {
        $this->reservar('10:00');

        $this->expectException(CupoNoDisponible::class);
        $this->reservar('10:15', cliente: ['telefono' => '3109998877']);
    }

    public function test_si_falla_no_deja_cliente_ni_cita_a_medias(): void
    {
        $this->reservar('10:00');

        try {
            $this->reservar('10:00', cliente: ['telefono' => '3109998877', 'nombre' => 'Fantasma']);
            $this->fail('Debió lanzar CupoNoDisponible.');
        } catch (CupoNoDisponible) {
            //
        }

        $this->assertSame(1, Cita::count());
        $this->assertNull(Cliente::firstWhere('nombre', 'Fantasma'));
    }

    public function test_una_cita_cancelada_libera_el_horario(): void
    {
        $primera = $this->reservar('10:00');
        $primera->update(['estado' => EstadoCita::Cancelada]);

        $segunda = $this->reservar('10:00', cliente: ['telefono' => '3109998877']);

        $this->assertSame('10:00', $segunda->inicia_en->format('H:i'));
    }

    public function test_rechaza_horas_fuera_del_horario_del_barbero(): void
    {
        $this->expectException(CupoNoDisponible::class);
        $this->reservar('12:15'); // almuerzo
    }

    public function test_rechaza_un_servicio_que_el_barbero_no_hace(): void
    {
        $otro = Servicio::factory()->create(['barberia_id' => $this->barberia->id, 'duracion_minutos' => 30]);

        $this->expectException(CupoNoDisponible::class);
        $this->reservar('10:00', [$otro->id]);
    }

    public function test_rechaza_servicios_ocultos(): void
    {
        $this->barba->update(['activo' => false]);

        $this->expectException(CupoNoDisponible::class);
        $this->reservar('10:00', [$this->barba->id]);
    }

    public function test_el_cliente_debe_respetar_la_anticipacion_minima(): void
    {
        Carbon::setTestNow('2026-10-12 09:30:00');

        $this->expectException(CupoNoDisponible::class);
        $this->reservar('10:00'); // faltan 30 min y la barbería exige 60
    }

    public function test_el_personal_puede_reservar_sin_anticipacion(): void
    {
        Carbon::setTestNow('2026-10-12 09:30:00');

        $cita = $this->reservar('09:45', desde: Carbon::parse('2026-10-12 00:00'));

        $this->assertSame('09:45', $cita->inicia_en->format('H:i'));
    }

    public function test_dos_barberos_pueden_tener_citas_a_la_misma_hora(): void
    {
        $otro = \App\Models\Barbero::factory()->create(['barberia_id' => $this->barberia->id]);
        $otro->servicios()->attach($this->corte->id);
        $this->horario($otro, 1, '09:00', '12:00');

        $this->reservar('10:00');
        $cita = app(ReservarCita::class)(
            $otro, [$this->corte->id], Carbon::parse('2026-10-12 10:00'),
            ['nombre' => 'Ana', 'telefono' => '3115556677'], OrigenCita::Manual,
        );

        $this->assertSame($otro->id, $cita->barbero_id);
        $this->assertSame(2, Cita::count());
    }
}
