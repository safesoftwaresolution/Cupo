<?php

namespace Tests\Feature;

use App\Enums\EstadoCita;
use App\Enums\OrigenCita;
use App\Models\Barberia;
use App\Models\Barbero;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\ArmaBarberia;
use Tests\TestCase;

class ReservaPublicaTest extends TestCase
{
    use ArmaBarberia, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->armarBarberia();
    }

    private function url(string $ruta = '', ?Barberia $barberia = null): string
    {
        return '/b/'.($barberia ?? $this->barberia)->slug.$ruta;
    }

    private function datos(array $extra = []): array
    {
        return [
            'servicios' => [$this->corte->id],
            'barbero_id' => $this->barbero->id,
            'inicia_en' => '2026-10-12 10:00',
            'nombre' => 'Juan Pérez',
            'telefono' => '300 123 4567',
            'acepta' => true,
            ...$extra,
        ];
    }

    private function citaExistente(array $extra = []): Cita
    {
        return Cita::factory()->create([
            'barberia_id' => $this->barberia->id,
            'barbero_id' => $this->barbero->id,
            'inicia_en' => Carbon::parse('2026-10-12 10:00'),
            'termina_en' => Carbon::parse('2026-10-12 10:30'),
            'estado' => EstadoCita::Confirmada,
            ...$extra,
        ]);
    }

    // --- Página de reserva ------------------------------------------------

    public function test_muestra_los_servicios_y_barberos_de_la_barberia(): void
    {
        $this->get($this->url())->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('reserva/Show')
                ->where('barberia.nombre', $this->barberia->nombre)
                ->has('servicios', 2)
                ->has('barberos', 1)
                ->where('hoy', '2026-10-11')
        );
    }

    public function test_no_ofrece_barberos_inactivos_ni_sin_horario(): void
    {
        Barbero::factory()->create(['barberia_id' => $this->barberia->id, 'activo' => false]);
        $sinHorario = Barbero::factory()->create(['barberia_id' => $this->barberia->id]);
        $sinHorario->servicios()->attach($this->corte->id);

        $this->get($this->url())->assertInertia(fn (Assert $page) => $page->has('barberos', 1));
    }

    public function test_no_mezcla_datos_de_otra_barberia(): void
    {
        $otra = Barberia::factory()->create();
        Servicio::factory()->create(['barberia_id' => $otra->id, 'nombre' => 'Servicio ajeno']);
        $ajeno = Barbero::factory()->create(['barberia_id' => $otra->id]);
        $this->horario($ajeno, 1, '09:00', '12:00');

        $this->get($this->url())->assertInertia(
            fn (Assert $page) => $page->has('servicios', 2)->has('barberos', 1)
        );
    }

    public function test_una_barberia_inexistente_o_inactiva_da_404(): void
    {
        $this->get('/b/no-existe')->assertNotFound();

        $this->barberia->update(['activa' => false]);
        $this->get($this->url())->assertNotFound();
    }

    // --- Disponibilidad -------------------------------------------------------

    public function test_devuelve_las_horas_libres_del_dia(): void
    {
        $this->citaExistente();

        $respuesta = $this->getJson($this->url('/disponibilidad').'?'.http_build_query([
            'servicios' => [$this->corte->id],
            'fecha' => '2026-10-12',
        ]))->assertOk();

        $horas = $respuesta->json('horas');
        $this->assertContains('09:30', $horas);
        $this->assertNotContains('10:00', $horas);
        $this->assertContains('10:30', $horas);
        $this->assertSame(30, $respuesta->json('duracion'));
        $this->assertSame($this->barbero->id, $respuesta->json('barberos.0.id'));
    }

    public function test_la_disponibilidad_se_puede_filtrar_por_barbero(): void
    {
        $otro = Barbero::factory()->create(['barberia_id' => $this->barberia->id]);
        $otro->servicios()->attach($this->corte->id);
        $this->horario($otro, 1, '15:00', '16:00');

        $respuesta = $this->getJson($this->url('/disponibilidad').'?'.http_build_query([
            'servicios' => [$this->corte->id],
            'barbero_id' => $otro->id,
            'fecha' => '2026-10-12',
        ]))->assertOk();

        $this->assertSame(['15:00', '15:15', '15:30'], $respuesta->json('horas'));
    }

    public function test_valida_los_parametros_de_disponibilidad(): void
    {
        $this->getJson($this->url('/disponibilidad').'?fecha=2026-10-12')->assertUnprocessable()->assertJsonValidationErrors('servicios');

        $this->getJson($this->url('/disponibilidad').'?'.http_build_query(['servicios' => [$this->corte->id], 'fecha' => '2026-10-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('fecha');
    }

    // --- Reservar ---------------------------------------------------------------

    public function test_el_cliente_reserva_y_ve_su_confirmacion(): void
    {
        $respuesta = $this->post($this->url('/reservar'), $this->datos());

        $cita = Cita::firstOrFail();
        $respuesta->assertRedirect($this->url('/cita/'.$cita->codigo));

        $this->assertSame(OrigenCita::Web, $cita->origen);
        $this->assertSame($this->barbero->id, $cita->barbero_id);
        $this->assertSame('2026-10-12 10:00', $cita->inicia_en->format('Y-m-d H:i'));
        $this->assertSame('573001234567', $cita->cliente->telefono);
        $this->assertTrue($cita->cliente->acepta_whatsapp);
    }

    public function test_sin_barbero_elegido_asigna_uno_disponible(): void
    {
        $this->citaExistente(); // el barbero 1 está ocupado a las 10:00
        $libre = Barbero::factory()->create(['barberia_id' => $this->barberia->id]);
        $libre->servicios()->attach($this->corte->id);
        $this->horario($libre, 1, '09:00', '12:00');

        $this->post($this->url('/reservar'), $this->datos(['barbero_id' => null]))->assertRedirect();

        $this->assertSame($libre->id, Cita::latest('id')->first()->barbero_id);
    }

    public function test_exige_autorizar_el_tratamiento_de_datos(): void
    {
        $this->post($this->url('/reservar'), $this->datos(['acepta' => false]))->assertSessionHasErrors('acepta');

        $this->assertSame(0, Cita::count());
        $this->assertSame(0, Cliente::count());
    }

    public function test_rechaza_celulares_no_colombianos(): void
    {
        foreach (['12345', '2001234567', '+1 415 555 0100', 'abcdefghij'] as $malo) {
            $this->post($this->url('/reservar'), $this->datos(['telefono' => $malo]))->assertSessionHasErrors('telefono');
        }

        $this->assertSame(0, Cita::count());
    }

    public function test_acepta_el_celular_con_o_sin_indicativo(): void
    {
        $this->post($this->url('/reservar'), $this->datos(['telefono' => '+57 310 999 8877']))->assertSessionHasNoErrors();

        $this->assertSame('573109998877', Cliente::first()->telefono);
    }

    public function test_el_campo_trampa_descarta_a_los_bots(): void
    {
        $this->post($this->url('/reservar'), $this->datos(['sitio_web' => 'http://spam.example']))->assertSessionHasErrors('sitio_web');

        $this->assertSame(0, Cita::count());
    }

    public function test_un_horario_ocupado_se_rechaza_con_un_mensaje_claro(): void
    {
        $this->citaExistente();

        $this->post($this->url('/reservar'), $this->datos())
            ->assertSessionHasErrors(['inicia_en' => 'Ese horario ya no está disponible. Elige otro.']);

        $this->assertSame(1, Cita::count());
        $this->assertSame(0, Cliente::where('telefono', '573001234567')->count());
    }

    public function test_rechaza_un_servicio_de_otra_barberia(): void
    {
        $ajeno = Servicio::factory()->create(['barberia_id' => Barberia::factory()->create()->id]);

        $this->post($this->url('/reservar'), $this->datos(['servicios' => [$ajeno->id]]))->assertSessionHasErrors('inicia_en');

        $this->assertSame(0, Cita::count());
    }

    public function test_no_se_puede_reservar_con_un_barbero_de_otra_barberia(): void
    {
        $ajeno = Barbero::factory()->create(['barberia_id' => Barberia::factory()->create()->id]);

        $this->post($this->url('/reservar'), $this->datos(['barbero_id' => $ajeno->id]))->assertNotFound();

        $this->assertSame(0, Cita::count());
    }

    public function test_limita_la_cantidad_de_reservas_por_minuto(): void
    {
        $ultimo = null;
        for ($i = 0; $i < 11; $i++) {
            $ultimo = $this->post($this->url('/reservar'), ['nombre' => 'x']); // inválida: igual cuenta
        }

        $ultimo->assertStatus(429);
    }

    // --- Consultar y cancelar -------------------------------------------------------

    public function test_el_cliente_consulta_su_cita_con_el_codigo(): void
    {
        $cita = $this->citaExistente();
        $cita->servicios()->attach($this->corte->id, ['nombre' => 'Corte', 'duracion_minutos' => 30, 'precio' => 20000]);

        $this->get($this->url('/cita/'.$cita->codigo))->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('reserva/Cita')
                ->where('cita.codigo', $cita->codigo)
                ->where('cita.estado', 'confirmada')
                ->where('cita.cancelable', true)
                ->where('cita.servicios', ['Corte'])
        );
    }

    public function test_un_codigo_inexistente_da_404(): void
    {
        $this->get($this->url('/cita/zzzzzzzzzz'))->assertNotFound();
    }

    public function test_un_codigo_de_otra_barberia_no_se_puede_ver_ni_cancelar(): void
    {
        $otra = Barberia::factory()->create();
        $ajena = Cita::factory()->create(['barberia_id' => $otra->id]);

        $this->get($this->url('/cita/'.$ajena->codigo))->assertNotFound();
        $this->post($this->url('/cita/'.$ajena->codigo.'/cancelar'))->assertNotFound();

        $this->assertSame(EstadoCita::Pendiente, $ajena->fresh()->estado);
    }

    public function test_el_cliente_cancela_y_el_cupo_queda_libre(): void
    {
        $cita = $this->citaExistente();

        $this->post($this->url('/cita/'.$cita->codigo.'/cancelar'))->assertSessionHasNoErrors();

        $cita->refresh();
        $this->assertSame(EstadoCita::Cancelada, $cita->estado);
        $this->assertNotNull($cita->cancelada_en);
        $this->assertSame('Cancelada por el cliente', $cita->motivo_cancelacion);

        // Otra persona ya puede tomar ese horario.
        $this->post($this->url('/reservar'), $this->datos(['telefono' => '3109998877']))->assertSessionHasNoErrors();
    }

    public function test_no_se_cancela_una_cita_que_ya_empezo_ni_una_cancelada(): void
    {
        Carbon::setTestNow('2026-10-12 10:15:00');
        $enCurso = $this->citaExistente();

        $this->post($this->url('/cita/'.$enCurso->codigo.'/cancelar'))->assertSessionHasErrors('cita');
        $this->assertSame(EstadoCita::Confirmada, $enCurso->fresh()->estado);

        $cancelada = $this->citaExistente(['estado' => EstadoCita::Cancelada, 'inicia_en' => Carbon::parse('2026-10-12 15:00'), 'termina_en' => Carbon::parse('2026-10-12 15:30')]);
        $this->post($this->url('/cita/'.$cancelada->codigo.'/cancelar'))->assertSessionHasErrors('cita');
    }
}
