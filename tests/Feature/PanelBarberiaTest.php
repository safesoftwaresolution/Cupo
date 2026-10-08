<?php

namespace Tests\Feature;

use App\Enums\EstadoCita;
use App\Enums\Rol;
use App\Models\Barberia;
use App\Models\Barbero;
use App\Models\Cita;
use App\Models\Servicio;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PanelBarberiaTest extends TestCase
{
    use RefreshDatabase;

    private Barberia $barberia;

    private User $dueno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barberia = Barberia::factory()->create();
        $this->dueno = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Dueno]);
    }

    private function servicio(?Barberia $barberia = null): Servicio
    {
        return Servicio::factory()->create(['barberia_id' => ($barberia ?? $this->barberia)->id]);
    }

    private function barbero(?Barberia $barberia = null): Barbero
    {
        return Barbero::factory()->create(['barberia_id' => ($barberia ?? $this->barberia)->id]);
    }

    /**
     * Crea una franja dentro del contexto de la barbería, como lo haría una petición real.
     */
    private function franja(Barbero $barbero, int $dia, string $inicio, string $fin): void
    {
        app(TenantContext::class)->ejecutarEn($this->barberia, fn () => $barbero->horarios()->create([
            'dia_semana' => $dia,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
        ]));
    }

    private function datosBarbero(array $extra = []): array
    {
        return [
            'nombre' => 'Carlos Gómez',
            'telefono' => '3001234567',
            'comision_porcentaje' => 50,
            'color' => '#ff8800',
            'activo' => true,
            'servicios' => [],
            ...$extra,
        ];
    }

    // --- Acceso -----------------------------------------------------------

    public function test_los_invitados_van_al_login(): void
    {
        $this->get('/barberos')->assertRedirect('/login');
        $this->get('/servicios')->assertRedirect('/login');
    }

    public function test_un_usuario_sin_barberia_no_entra_al_panel(): void
    {
        $sinBarberia = User::factory()->create(['barberia_id' => null]);

        $this->actingAs($sinBarberia)->get('/dashboard')->assertForbidden();
    }

    public function test_solo_el_dueno_configura_la_barberia(): void
    {
        foreach ([Rol::Recepcion, Rol::Barbero] as $rol) {
            $usuario = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => $rol]);

            $this->actingAs($usuario)->get('/barberos')->assertForbidden();
            $this->actingAs($usuario)->get('/servicios')->assertForbidden();
            $this->actingAs($usuario)->post('/servicios', [])->assertForbidden();
            $this->actingAs($usuario)->get('/dashboard')->assertOk();
        }
    }

    // --- Servicios --------------------------------------------------------

    public function test_el_dueno_crea_un_servicio(): void
    {
        $this->actingAs($this->dueno)->post('/servicios', [
            'nombre' => 'Fade',
            'duracion_minutos' => 45,
            'precio' => 25000,
            'activo' => true,
        ])->assertRedirect();

        $servicio = Servicio::firstWhere('nombre', 'Fade');
        $this->assertNotNull($servicio);
        $this->assertSame($this->barberia->id, $servicio->barberia_id);
    }

    public function test_el_listado_de_servicios_solo_muestra_los_propios(): void
    {
        $this->servicio();
        $this->servicio();
        $this->servicio(Barberia::factory()->create());

        $this->actingAs($this->dueno)->get('/servicios')->assertInertia(
            fn (Assert $page) => $page->component('servicios/Index')->has('servicios', 2)
        );
    }

    public function test_valida_los_datos_del_servicio(): void
    {
        $this->actingAs($this->dueno)->post('/servicios', [
            'nombre' => '',
            'duracion_minutos' => 2,
            'precio' => -5,
            'activo' => true,
        ])->assertSessionHasErrors(['nombre', 'duracion_minutos', 'precio']);
    }

    public function test_no_se_puede_editar_ni_borrar_un_servicio_de_otra_barberia(): void
    {
        $ajeno = $this->servicio(Barberia::factory()->create());
        $datos = ['nombre' => 'Hackeado', 'duracion_minutos' => 30, 'precio' => 1, 'activo' => true];

        $this->actingAs($this->dueno)->put("/servicios/{$ajeno->id}", $datos)->assertNotFound();
        $this->actingAs($this->dueno)->delete("/servicios/{$ajeno->id}")->assertNotFound();

        $this->assertNotSame('Hackeado', $ajeno->fresh()->nombre);
    }

    public function test_eliminar_un_servicio_lo_oculta_sin_borrar_el_historial(): void
    {
        $servicio = $this->servicio();

        $this->actingAs($this->dueno)->delete("/servicios/{$servicio->id}")->assertRedirect();

        $this->assertSoftDeleted($servicio);
    }

    // --- Barberos ---------------------------------------------------------

    public function test_el_dueno_crea_un_barbero_con_sus_servicios(): void
    {
        $servicio = $this->servicio();

        $this->actingAs($this->dueno)
            ->post('/barberos', $this->datosBarbero(['servicios' => [$servicio->id]]))
            ->assertRedirect();

        $barbero = Barbero::firstWhere('nombre', 'Carlos Gómez');
        $this->assertSame($this->barberia->id, $barbero->barberia_id);
        $this->assertSame([$servicio->id], $barbero->servicios()->pluck('servicios.id')->all());
    }

    public function test_no_se_pueden_asignar_servicios_de_otra_barberia(): void
    {
        $ajeno = $this->servicio(Barberia::factory()->create());

        $this->actingAs($this->dueno)
            ->post('/barberos', $this->datosBarbero(['servicios' => [$ajeno->id]]))
            ->assertSessionHasErrors('servicios.0');

        $this->assertSame(0, Barbero::count());
    }

    public function test_la_comision_debe_estar_entre_0_y_100(): void
    {
        $this->actingAs($this->dueno)
            ->post('/barberos', $this->datosBarbero(['comision_porcentaje' => 150]))
            ->assertSessionHasErrors('comision_porcentaje');
    }

    public function test_actualizar_un_barbero_reemplaza_sus_servicios(): void
    {
        $barbero = $this->barbero();
        $viejo = $this->servicio();
        $nuevo = $this->servicio();
        $barbero->servicios()->attach($viejo);

        $this->actingAs($this->dueno)
            ->put("/barberos/{$barbero->id}", $this->datosBarbero(['servicios' => [$nuevo->id]]))
            ->assertRedirect();

        $this->assertSame([$nuevo->id], $barbero->servicios()->pluck('servicios.id')->all());
    }

    public function test_no_se_puede_editar_un_barbero_de_otra_barberia(): void
    {
        $ajeno = $this->barbero(Barberia::factory()->create());

        $this->actingAs($this->dueno)->put("/barberos/{$ajeno->id}", $this->datosBarbero())->assertNotFound();
        $this->actingAs($this->dueno)->delete("/barberos/{$ajeno->id}")->assertNotFound();
        $this->actingAs($this->dueno)->get("/barberos/{$ajeno->id}/horario")->assertNotFound();
    }

    public function test_no_se_elimina_un_barbero_con_citas_pendientes(): void
    {
        $barbero = $this->barbero();
        Cita::factory()->create([
            'barberia_id' => $this->barberia->id,
            'barbero_id' => $barbero->id,
            'inicia_en' => now()->addDays(2),
            'termina_en' => now()->addDays(2)->addMinutes(30),
            'estado' => EstadoCita::Confirmada,
        ]);

        $this->actingAs($this->dueno)->delete("/barberos/{$barbero->id}")->assertSessionHasErrors('barbero');

        $this->assertNotSoftDeleted($barbero);
    }

    public function test_se_elimina_un_barbero_sin_citas_pendientes(): void
    {
        $barbero = $this->barbero();

        $this->actingAs($this->dueno)->delete("/barberos/{$barbero->id}")->assertRedirect();

        $this->assertSoftDeleted($barbero);
    }

    // --- Horarios ---------------------------------------------------------

    public function test_guarda_el_horario_semanal_reemplazando_el_anterior(): void
    {
        $barbero = $this->barbero();
        $this->franja($barbero, 7, '08:00', '12:00');

        $this->actingAs($this->dueno)->put("/barberos/{$barbero->id}/horario", [
            'franjas' => [
                ['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '12:00'],
                ['dia_semana' => 1, 'hora_inicio' => '13:00', 'hora_fin' => '19:00'],
            ],
        ])->assertRedirect();

        $franjas = $barbero->horarios()->orderBy('hora_inicio')->get();
        $this->assertCount(2, $franjas);
        $this->assertSame([1, 1], $franjas->pluck('dia_semana')->all());
        $this->assertSame($this->barberia->id, $franjas->first()->barberia_id);
    }

    public function test_se_puede_dejar_al_barbero_sin_horario(): void
    {
        $barbero = $this->barbero();
        $this->franja($barbero, 2, '09:00', '12:00');

        $this->actingAs($this->dueno)->put("/barberos/{$barbero->id}/horario", ['franjas' => []])->assertRedirect();

        $this->assertSame(0, $barbero->horarios()->count());
    }

    public function test_rechaza_franjas_que_se_cruzan_en_el_mismo_dia(): void
    {
        $barbero = $this->barbero();

        $this->actingAs($this->dueno)->put("/barberos/{$barbero->id}/horario", [
            'franjas' => [
                ['dia_semana' => 3, 'hora_inicio' => '09:00', 'hora_fin' => '13:00'],
                ['dia_semana' => 3, 'hora_inicio' => '12:00', 'hora_fin' => '18:00'],
            ],
        ])->assertSessionHasErrors('franjas');

        $this->assertSame(0, $barbero->horarios()->count());
    }

    public function test_rechaza_una_franja_que_termina_antes_de_empezar(): void
    {
        $barbero = $this->barbero();

        $this->actingAs($this->dueno)->put("/barberos/{$barbero->id}/horario", [
            'franjas' => [['dia_semana' => 1, 'hora_inicio' => '15:00', 'hora_fin' => '10:00']],
        ])->assertSessionHasErrors('franjas.0.hora_fin');
    }

    public function test_rechaza_dias_fuera_de_rango(): void
    {
        $barbero = $this->barbero();

        $this->actingAs($this->dueno)->put("/barberos/{$barbero->id}/horario", [
            'franjas' => [['dia_semana' => 8, 'hora_inicio' => '09:00', 'hora_fin' => '10:00']],
        ])->assertSessionHasErrors('franjas.0.dia_semana');
    }

    // --- Dashboard --------------------------------------------------------

    public function test_el_dashboard_marca_los_pasos_de_configuracion_completados(): void
    {
        $this->actingAs($this->dueno)->get('/dashboard')->assertInertia(
            fn (Assert $page) => $page->component('Dashboard')
                ->where('pasos.0.hecho', false)
                ->where('pasos.1.hecho', false)
                ->where('pasos.2.hecho', false)
        );

        $this->servicio();
        $barbero = $this->barbero();
        $this->franja($barbero, 1, '09:00', '12:00');

        $this->actingAs($this->dueno)->get('/dashboard')->assertInertia(
            fn (Assert $page) => $page
                ->where('pasos.0.hecho', true)
                ->where('pasos.1.hecho', true)
                ->where('pasos.2.hecho', true)
        );
    }
}
