<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Barberia;
use App\Models\Barbero;
use App\Models\User;
use App\Models\Venta;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\ArmaBarberia;
use Tests\TestCase;

class AjustesBarberiaTest extends TestCase
{
    use ArmaBarberia, RefreshDatabase;

    private User $dueno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->armarBarberia();
        app(TenantContext::class)->olvidar();

        $this->dueno = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Dueno]);
    }

    /**
     * Cambia un ajuste directo en la base. Se descarta la barbería ya cargada en el usuario de la prueba:
     * en la vida real cada petición carga al usuario de nuevo, pero aquí es el mismo objeto.
     *
     * @param  array<string, mixed>  $cambios
     */
    private function ajustar(array $cambios): void
    {
        $this->barberia->update($cambios);
        $this->dueno->unsetRelation('barberia');
    }

    private function datos(array $extra = []): array
    {
        return [
            'nombre' => 'Barbería El Corte',
            'telefono' => '300 123 4567',
            'direccion' => 'Calle 10 # 5-20',
            'ciudad' => 'Medellín',
            'intervalo_agenda' => 30,
            'anticipacion_minima' => 120,
            'dias_reserva' => 14,
            'maneja_comisiones' => true,
            'recordatorios_activos' => true,
            'recordatorio_horas' => 12,
            ...$extra,
        ];
    }

    // --- Acceso y guardado ----------------------------------------------------------

    public function test_solo_el_dueno_entra_a_los_ajustes(): void
    {
        $this->get('/ajustes/barberia')->assertRedirect('/login');

        foreach ([Rol::Recepcion, Rol::Barbero] as $rol) {
            $usuario = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => $rol]);
            $this->actingAs($usuario)->get('/ajustes/barberia')->assertForbidden();
            $this->actingAs($usuario)->put('/ajustes/barberia', $this->datos())->assertForbidden();
        }

        $this->actingAs($this->dueno)->get('/ajustes/barberia')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('ajustes/Barberia')
                ->where('ajustes.nombre', $this->barberia->nombre)
                ->where('ajustes.maneja_comisiones', true)
                ->where('opciones.horasRecordatorio', [2, 3, 6, 12, 24, 48])
        );
    }

    public function test_guarda_los_ajustes(): void
    {
        $this->actingAs($this->dueno)->put('/ajustes/barberia', $this->datos())->assertSessionHasNoErrors();

        $barberia = $this->barberia->fresh();
        $this->assertSame('Barbería El Corte', $barberia->nombre);
        $this->assertSame('Medellín', $barberia->ciudad);
        $this->assertSame(30, $barberia->intervalo_agenda);
        $this->assertSame(120, $barberia->anticipacion_minima);
        $this->assertSame(14, $barberia->dias_reserva);
        $this->assertSame(12, $barberia->recordatorio_horas);
    }

    public function test_el_enlace_publico_no_cambia_al_renombrar(): void
    {
        $slug = $this->barberia->slug;

        $this->actingAs($this->dueno)->put('/ajustes/barberia', $this->datos(['nombre' => 'Otro nombre']));

        $this->assertSame($slug, $this->barberia->fresh()->slug, 'Cambiar el nombre no debe romper los enlaces ya compartidos.');
    }

    public function test_valida_los_valores(): void
    {
        foreach ([
            ['intervalo_agenda', 7],
            ['anticipacion_minima', 45],
            ['dias_reserva', 0],
            ['dias_reserva', 91],
            ['recordatorio_horas', 5],
            ['nombre', ''],
            ['telefono', 'llámame'],
            ['maneja_comisiones', 'quizás'],
        ] as [$campo, $valor]) {
            $this->actingAs($this->dueno)->put('/ajustes/barberia', $this->datos([$campo => $valor]))->assertSessionHasErrors($campo);
        }

        $this->assertNotSame('', $this->barberia->fresh()->nombre);
    }

    public function test_solo_cambia_la_barberia_del_usuario(): void
    {
        $otra = Barberia::factory()->create(['nombre' => 'Intacta']);

        $this->actingAs($this->dueno)->put('/ajustes/barberia', $this->datos());

        $this->assertSame('Intacta', $otra->fresh()->nombre);
    }

    public function test_el_intervalo_de_la_agenda_cambia_los_cupos_que_se_ofrecen(): void
    {
        $this->actingAs($this->dueno)->put('/ajustes/barberia', $this->datos(['intervalo_agenda' => 30, 'anticipacion_minima' => 0]));

        $horas = $this->getJson("/b/{$this->barberia->slug}/disponibilidad?".http_build_query([
            'servicios' => [$this->corte->id],
            'fecha' => '2026-10-12',
        ]))->json('horas');

        $this->assertContains('09:30', $horas);
        $this->assertNotContains('09:15', $horas);
    }

    // --- Sin comisiones ------------------------------------------------------------------

    private function cobrarUnCorte(): Venta
    {
        $cita = $this->reservar('10:00');
        Carbon::setTestNow('2026-10-12 12:00:00');

        $this->actingAs($this->dueno)->post("/caja/citas/{$cita->id}/cobrar", ['metodo_pago' => 'efectivo', 'propina' => 2000])->assertSessionHasNoErrors();

        return Venta::query()->latest('id')->firstOrFail();
    }

    public function test_con_comisiones_activadas_se_calcula_la_comision(): void
    {
        $venta = $this->cobrarUnCorte();

        $this->assertSame(10000, $venta->comision_monto);
        $this->assertSame('50.00', $venta->comision_porcentaje);
    }

    public function test_sin_comisiones_las_ventas_nuevas_quedan_en_cero(): void
    {
        $this->ajustar(['maneja_comisiones' => false]);

        $venta = $this->cobrarUnCorte();

        $this->assertSame(0, $venta->comision_monto);
        $this->assertSame('0.00', $venta->comision_porcentaje);
        $this->assertSame(22000, $venta->total, 'Lo que paga el cliente no cambia.');
    }

    public function test_apagar_las_comisiones_no_toca_las_ventas_anteriores(): void
    {
        $antes = $this->cobrarUnCorte();

        $this->actingAs($this->dueno)->put('/ajustes/barberia', $this->datos(['maneja_comisiones' => false]));

        $this->assertSame(10000, $antes->fresh()->comision_monto);
    }

    public function test_sin_comisiones_no_existe_la_liquidacion(): void
    {
        $this->ajustar(['maneja_comisiones' => false]);

        $this->actingAs($this->dueno)->get('/comisiones')->assertNotFound();
        $this->actingAs($this->dueno)->get('/comisiones/exportar')->assertNotFound();

        $this->ajustar(['maneja_comisiones' => true]);
        $this->actingAs($this->dueno)->get('/comisiones')->assertOk();
    }

    public function test_el_barbero_se_crea_sin_comision_cuando_la_barberia_no_las_maneja(): void
    {
        $this->ajustar(['maneja_comisiones' => false]);

        $this->actingAs($this->dueno)->post('/barberos', [
            'nombre' => 'Sin Comisión',
            'activo' => true,
            'servicios' => [],
        ])->assertSessionHasNoErrors();

        $barbero = Barbero::firstWhere('nombre', 'Sin Comisión');
        $this->assertNotNull($barbero);
        $this->assertSame('50.00', $barbero->comision_porcentaje, 'Conserva el valor por defecto por si luego se activan las comisiones.');
    }

    public function test_editar_un_barbero_sin_comisiones_conserva_su_porcentaje(): void
    {
        $this->ajustar(['maneja_comisiones' => false]);
        $this->barbero->update(['comision_porcentaje' => 65]);

        $this->actingAs($this->dueno)->put("/barberos/{$this->barbero->id}", [
            'nombre' => 'Nuevo nombre',
            'activo' => true,
            'servicios' => [],
        ])->assertSessionHasNoErrors();

        $this->assertSame('65.00', $this->barbero->fresh()->comision_porcentaje);
    }

    public function test_con_comisiones_activadas_el_barbero_la_exige(): void
    {
        $this->actingAs($this->dueno)->post('/barberos', ['nombre' => 'Sin dato', 'activo' => true, 'servicios' => []])
            ->assertSessionHasErrors('comision_porcentaje');
    }

    public function test_el_panel_recibe_si_se_manejan_comisiones(): void
    {
        $this->actingAs($this->dueno)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('barberia.maneja_comisiones', true));

        $this->ajustar(['maneja_comisiones' => false]);

        $this->actingAs($this->dueno)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('barberia.maneja_comisiones', false));
    }
}
