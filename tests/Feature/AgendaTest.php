<?php

namespace Tests\Feature;

use App\Enums\EstadoCita;
use App\Enums\OrigenCita;
use App\Enums\Rol;
use App\Models\Barberia;
use App\Models\Barbero;
use App\Models\Cita;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\ArmaBarberia;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use ArmaBarberia, RefreshDatabase;

    private User $dueno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->armarBarberia();
        // En el panel la barbería la define el usuario autenticado, no un contexto fijo.
        app(TenantContext::class)->olvidar();

        $this->dueno = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Dueno]);
    }

    private function cita(array $extra = []): Cita
    {
        return Cita::factory()->create([
            'barberia_id' => $this->barberia->id,
            'barbero_id' => $this->barbero->id,
            'inicia_en' => Carbon::parse('2026-10-12 10:00'),
            'termina_en' => Carbon::parse('2026-10-12 10:30'),
            'estado' => EstadoCita::Pendiente,
            ...$extra,
        ]);
    }

    private function datosCita(array $extra = []): array
    {
        return [
            'barbero_id' => $this->barbero->id,
            'servicios' => [$this->corte->id],
            'inicia_en' => '2026-10-12 10:00',
            'nombre' => 'Carlos Ruiz',
            'telefono' => '3101112233',
            ...$extra,
        ];
    }

    // --- Acceso -----------------------------------------------------------------

    public function test_los_invitados_van_al_login(): void
    {
        $this->get('/agenda')->assertRedirect('/login');
    }

    public function test_el_dueno_y_la_recepcion_ven_la_agenda_pero_el_barbero_no(): void
    {
        $this->actingAs($this->dueno)->get('/agenda')->assertOk();

        $recepcion = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Recepcion]);
        $this->actingAs($recepcion)->get('/agenda')->assertOk();

        $barbero = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Barbero]);
        $this->actingAs($barbero)->get('/agenda')->assertForbidden();
    }

    // --- Ver el día ------------------------------------------------------------

    public function test_muestra_solo_las_citas_del_dia_y_de_su_barberia(): void
    {
        $this->cita();
        $this->cita(['inicia_en' => Carbon::parse('2026-10-12 11:00'), 'termina_en' => Carbon::parse('2026-10-12 11:30')]);
        $this->cita(['inicia_en' => Carbon::parse('2026-10-13 10:00'), 'termina_en' => Carbon::parse('2026-10-13 10:30')]); // otro día
        Cita::factory()->create([ // otra barbería, mismo día
            'barberia_id' => Barberia::factory()->create()->id,
            'inicia_en' => Carbon::parse('2026-10-12 10:00'),
            'termina_en' => Carbon::parse('2026-10-12 10:30'),
        ]);

        $this->actingAs($this->dueno)->get('/agenda?fecha=2026-10-12')->assertInertia(
            fn (Assert $page) => $page->component('agenda/Index')
                ->where('fecha', '2026-10-12')
                ->has('citas', 2)
                ->has('barberos', 1)
                ->where('barberos.0.franjas.0.inicio', '09:00')
                ->where('citas.0.inicia', '10:00')
        );
    }

    public function test_una_fecha_invalida_cae_en_hoy(): void
    {
        $this->actingAs($this->dueno)->get('/agenda?fecha=basura')->assertInertia(
            fn (Assert $page) => $page->where('fecha', '2026-10-11')
        );
    }

    public function test_cada_cita_trae_las_transiciones_que_permite_su_estado(): void
    {
        $this->cita(['estado' => EstadoCita::Completada]);

        $this->actingAs($this->dueno)->get('/agenda?fecha=2026-10-12')->assertInertia(
            fn (Assert $page) => $page->where('citas.0.transiciones', [])
        );
    }

    // --- Nueva cita ---------------------------------------------------------------

    public function test_agenda_una_cita_manual(): void
    {
        $this->actingAs($this->dueno)->post('/agenda/citas', $this->datosCita())->assertRedirect();

        $cita = Cita::firstOrFail();
        $this->assertSame(OrigenCita::Manual, $cita->origen);
        $this->assertSame($this->barberia->id, $cita->barberia_id);
        $this->assertSame('573101112233', $cita->cliente->telefono);
        $this->assertFalse($cita->cliente->acepta_whatsapp, 'El personal no otorga la autorización por el cliente.');
    }

    public function test_el_personal_puede_agendar_a_quien_llega_sin_anticipacion(): void
    {
        Carbon::setTestNow('2026-10-12 09:30:00');

        $this->actingAs($this->dueno)->post('/agenda/citas', $this->datosCita(['inicia_en' => '2026-10-12 09:45']))->assertSessionHasNoErrors();

        $this->assertSame(1, Cita::count());
    }

    public function test_no_agenda_sobre_un_horario_ocupado(): void
    {
        $this->cita();

        $this->actingAs($this->dueno)->post('/agenda/citas', $this->datosCita())->assertSessionHasErrors('inicia_en');

        $this->assertSame(1, Cita::count());
    }

    public function test_no_agenda_con_un_barbero_de_otra_barberia(): void
    {
        $ajeno = Barbero::factory()->create(['barberia_id' => Barberia::factory()->create()->id]);

        $this->actingAs($this->dueno)->post('/agenda/citas', $this->datosCita(['barbero_id' => $ajeno->id]))->assertNotFound();
    }

    public function test_la_recepcion_tambien_agenda(): void
    {
        $recepcion = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Recepcion]);

        $this->actingAs($recepcion)->post('/agenda/citas', $this->datosCita())->assertSessionHasNoErrors();

        $this->assertSame(1, Cita::count());
    }

    public function test_el_barbero_no_puede_agendar_desde_el_panel(): void
    {
        $barbero = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Barbero]);

        $this->actingAs($barbero)->post('/agenda/citas', $this->datosCita())->assertForbidden();
    }

    public function test_la_disponibilidad_interna_incluye_horas_tempranas_de_hoy(): void
    {
        Carbon::setTestNow('2026-10-12 14:00:00');

        $horas = $this->actingAs($this->dueno)->getJson('/agenda/disponibilidad?'.http_build_query([
            'servicios' => [$this->corte->id],
            'barbero_id' => $this->barbero->id,
            'fecha' => '2026-10-12',
        ]))->assertOk()->json('barberos.0.horas');

        $this->assertContains('09:00', $horas, 'El personal puede registrar a quien ya fue atendido en la mañana.');
    }

    // --- Cambiar el estado -------------------------------------------------------------

    public function test_confirma_una_cita(): void
    {
        $cita = $this->cita();

        $this->actingAs($this->dueno)->patch("/agenda/citas/{$cita->id}/estado", ['estado' => 'confirmada'])->assertSessionHasNoErrors();

        $cita->refresh();
        $this->assertSame(EstadoCita::Confirmada, $cita->estado);
        $this->assertNotNull($cita->confirmada_en);
    }

    public function test_cancela_una_cita_y_libera_el_cupo(): void
    {
        $cita = $this->cita();

        $this->actingAs($this->dueno)->patch("/agenda/citas/{$cita->id}/estado", ['estado' => 'cancelada', 'motivo' => 'El cliente avisó'])
            ->assertSessionHasNoErrors();

        $cita->refresh();
        $this->assertSame(EstadoCita::Cancelada, $cita->estado);
        $this->assertSame('El cliente avisó', $cita->motivo_cancelacion);
        $this->assertNotNull($cita->cancelada_en);

        $this->actingAs($this->dueno)->post('/agenda/citas', $this->datosCita())->assertSessionHasNoErrors();
    }

    public function test_los_estados_finales_no_cambian(): void
    {
        foreach ([EstadoCita::Completada, EstadoCita::Cancelada, EstadoCita::NoAsistio] as $final) {
            $cita = $this->cita(['estado' => $final, 'inicia_en' => Carbon::parse('2026-10-12 15:00'), 'termina_en' => Carbon::parse('2026-10-12 15:30')]);

            $this->actingAs($this->dueno)->patch("/agenda/citas/{$cita->id}/estado", ['estado' => 'confirmada'])->assertSessionHasErrors('estado');

            $this->assertSame($final, $cita->fresh()->estado);
        }
    }

    public function test_rechaza_estados_que_no_existen(): void
    {
        $cita = $this->cita();

        $this->actingAs($this->dueno)->patch("/agenda/citas/{$cita->id}/estado", ['estado' => 'volando'])->assertSessionHasErrors('estado');
    }

    public function test_no_se_puede_cambiar_la_cita_de_otra_barberia(): void
    {
        $ajena = Cita::factory()->create(['barberia_id' => Barberia::factory()->create()->id]);

        $this->actingAs($this->dueno)->patch("/agenda/citas/{$ajena->id}/estado", ['estado' => 'confirmada'])->assertNotFound();

        $this->assertSame(EstadoCita::Pendiente, $ajena->fresh()->estado);
    }

    // --- Inicio --------------------------------------------------------------------------

    public function test_el_inicio_resume_las_citas_de_hoy_y_da_el_enlace_publico(): void
    {
        Carbon::setTestNow('2026-10-12 08:00:00');
        $this->cita(['precio_total' => 20000]);
        $this->cita(['inicia_en' => Carbon::parse('2026-10-12 11:00'), 'termina_en' => Carbon::parse('2026-10-12 11:30'), 'precio_total' => 15000]);
        $this->cita(['inicia_en' => Carbon::parse('2026-10-12 12:00'), 'termina_en' => Carbon::parse('2026-10-12 12:30'), 'estado' => EstadoCita::Cancelada, 'precio_total' => 99000]);

        $this->actingAs($this->dueno)->get('/dashboard')->assertInertia(
            fn (Assert $page) => $page->component('Dashboard')
                ->where('hoy.total', 2)
                ->where('hoy.ingresos', 35000)
                ->has('hoy.proximas', 2)
                ->where('urlReserva', url('/b/'.$this->barberia->slug))
        );
    }
}
