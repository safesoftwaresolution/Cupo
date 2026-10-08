<?php

namespace Tests\Feature;

use App\Enums\EstadoCita;
use App\Enums\MetodoPago;
use App\Enums\Rol;
use App\Models\Barberia;
use App\Models\Barbero;
use App\Models\CierreCaja;
use App\Models\Cita;
use App\Models\Servicio;
use App\Models\User;
use App\Models\Venta;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\ArmaBarberia;
use Tests\TestCase;

class CajaTest extends TestCase
{
    use ArmaBarberia, RefreshDatabase;

    private User $dueno;

    private User $recepcion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->armarBarberia();
        // En el panel la barbería la define el usuario autenticado, no un contexto fijo.
        app(TenantContext::class)->olvidar();

        $this->dueno = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Dueno]);
        $this->recepcion = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Recepcion]);

        // Las ventas de estas pruebas se hacen el lunes 12 por la tarde.
        Carbon::setTestNow('2026-10-12 15:00:00');
    }

    private function venta(array $extra = []): Venta
    {
        return Venta::factory()->create([
            'barberia_id' => $this->barberia->id,
            'barbero_id' => $this->barbero->id,
            'pagada_en' => Carbon::parse('2026-10-12 14:00'),
            ...$extra,
        ]);
    }

    // --- Permisos -------------------------------------------------------------------

    public function test_los_invitados_van_al_login(): void
    {
        $this->get('/caja')->assertRedirect('/login');
        $this->get('/comisiones')->assertRedirect('/login');
    }

    public function test_el_dueno_y_la_recepcion_ven_la_caja_pero_el_barbero_no(): void
    {
        $this->actingAs($this->dueno)->get('/caja')->assertOk();
        $this->actingAs($this->recepcion)->get('/caja')->assertOk();

        $barbero = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Barbero]);
        $this->actingAs($barbero)->get('/caja')->assertForbidden();
        $this->actingAs($barbero)->post('/caja/ventas', [])->assertForbidden();
    }

    public function test_anular_reabrir_y_comisiones_son_solo_del_dueno(): void
    {
        $venta = $this->venta();
        $cierre = CierreCaja::forceCreate(['barberia_id' => $this->barberia->id, 'fecha' => '2026-10-10', 'efectivo_esperado' => 0, 'efectivo_contado' => 0, 'diferencia' => 0]);

        $this->actingAs($this->recepcion)->patch("/caja/ventas/{$venta->id}/anular", ['motivo' => 'Error'])->assertForbidden();
        $this->actingAs($this->recepcion)->delete("/caja/cierre/{$cierre->id}")->assertForbidden();
        $this->actingAs($this->recepcion)->get('/comisiones')->assertForbidden();
        $this->actingAs($this->recepcion)->get('/comisiones/exportar')->assertForbidden();

        $this->assertFalse($venta->fresh()->estaAnulada());
    }

    // --- La caja del día --------------------------------------------------------------

    public function test_resume_el_dia_por_metodo_y_por_barbero(): void
    {
        $otro = Barbero::factory()->create(['barberia_id' => $this->barberia->id, 'nombre' => 'Otro Barbero']);

        // Barbero 1: 35.000 - 5.000 = 30.000 de servicios, comisión 15.000, propina 3.000, efectivo.
        $this->venta(['subtotal' => 35000, 'descuento' => 5000, 'propina' => 3000, 'total' => 33000, 'comision_monto' => 15000, 'metodo_pago' => MetodoPago::Efectivo]);
        // Barbero 1: 20.000 por Nequi, comisión 10.000.
        $this->venta(['subtotal' => 20000, 'total' => 20000, 'comision_monto' => 10000, 'metodo_pago' => MetodoPago::Nequi]);
        // Barbero 2: 15.000 en efectivo, comisión 6.000.
        $this->venta(['barbero_id' => $otro->id, 'subtotal' => 15000, 'total' => 15000, 'comision_monto' => 6000, 'metodo_pago' => MetodoPago::Efectivo]);
        // Anulada: no cuenta.
        $this->venta(['subtotal' => 99000, 'total' => 99000, 'comision_monto' => 49500, 'anulada_en' => now()]);
        // Otro día y otra barbería: no cuentan.
        $this->venta(['pagada_en' => Carbon::parse('2026-10-11 14:00'), 'subtotal' => 77000, 'total' => 77000]);
        Venta::factory()->create(['barberia_id' => Barberia::factory()->create()->id, 'pagada_en' => Carbon::parse('2026-10-12 14:00'), 'subtotal' => 55000, 'total' => 55000]);

        $this->actingAs($this->dueno)->get('/caja?fecha=2026-10-12')->assertInertia(
            fn (Assert $page) => $page->component('caja/Index')
                ->where('resumen.ventas', 3)
                ->where('resumen.total', 68000)
                ->where('resumen.servicios', 65000)
                ->where('resumen.propinas', 3000)
                ->where('resumen.comisiones', 31000)
                ->where('resumen.barberia', 34000)
                ->where('resumen.efectivo_esperado', 48000)
                ->where('porMetodo.0.valor', 'efectivo')
                ->where('porMetodo.0.total', 48000)
                ->where('porMetodo.1.total', 0)
                ->has('porBarbero', 2)
                ->where('porBarbero.0.a_pagar', 28000) // comisiones 15.000 + 10.000, más 3.000 de propina
                ->has('ventas', 4) // incluye la anulada, marcada
        );
    }

    public function test_lista_las_citas_del_dia_que_faltan_por_cobrar(): void
    {
        $sinCobrar = $this->reservar('10:00');
        $cobrada = $this->reservar('11:00', telefono: '3002223344');
        $this->venta(['cita_id' => $cobrada->id]);
        $cancelada = $this->reservar('15:00', telefono: '3005556677');
        $cancelada->update(['estado' => EstadoCita::Cancelada]);

        $this->actingAs($this->dueno)->get('/caja?fecha=2026-10-12')->assertInertia(
            fn (Assert $page) => $page->has('porCobrar', 1)->where('porCobrar.0.id', $sinCobrar->id)
        );
    }

    public function test_una_cita_con_venta_anulada_vuelve_a_aparecer_por_cobrar(): void
    {
        $cita = $this->reservar('10:00');
        $this->venta(['cita_id' => $cita->id, 'anulada_en' => now()]);

        $this->actingAs($this->dueno)->get('/caja?fecha=2026-10-12')->assertInertia(
            fn (Assert $page) => $page->has('porCobrar', 1)
        );
    }

    // --- Cobrar ------------------------------------------------------------------------

    public function test_cobra_una_cita(): void
    {
        $cita = $this->reservar('10:00');

        $this->actingAs($this->recepcion)->post("/caja/citas/{$cita->id}/cobrar", [
            'metodo_pago' => 'nequi',
            'descuento' => 2000,
            'propina' => 1000,
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Cobro registrado: $ 19.000.');

        $venta = Venta::firstOrFail();
        $this->assertSame(19000, $venta->total);
        $this->assertSame($this->recepcion->id, $venta->user_id);
        $this->assertSame(EstadoCita::Completada, $cita->fresh()->estado);
    }

    public function test_descuento_y_propina_son_opcionales(): void
    {
        $cita = $this->reservar('10:00');

        $this->actingAs($this->dueno)->post("/caja/citas/{$cita->id}/cobrar", ['metodo_pago' => 'efectivo'])->assertSessionHasNoErrors();

        $this->assertSame(20000, Venta::firstOrFail()->total);
    }

    public function test_valida_los_datos_del_cobro(): void
    {
        $cita = $this->reservar('10:00');

        foreach ([
            ['metodo_pago' => 'trueque'],
            ['metodo_pago' => 'efectivo', 'descuento' => -1],
            ['metodo_pago' => 'efectivo', 'propina' => 'mucha'],
            [],
        ] as $datos) {
            $this->actingAs($this->dueno)->post("/caja/citas/{$cita->id}/cobrar", $datos)->assertSessionHasErrors();
        }

        $this->assertSame(0, Venta::count());
    }

    public function test_cobrar_dos_veces_muestra_un_error_claro(): void
    {
        $cita = $this->reservar('10:00');
        $this->actingAs($this->dueno)->post("/caja/citas/{$cita->id}/cobrar", ['metodo_pago' => 'efectivo']);

        $this->actingAs($this->dueno)->post("/caja/citas/{$cita->id}/cobrar", ['metodo_pago' => 'efectivo'])
            ->assertSessionHasErrors(['cobro' => 'Esta cita ya fue cobrada.']);

        $this->assertSame(1, Venta::count());
    }

    public function test_no_se_cobra_la_cita_de_otra_barberia(): void
    {
        $ajena = Cita::factory()->create(['barberia_id' => Barberia::factory()->create()->id]);

        $this->actingAs($this->dueno)->post("/caja/citas/{$ajena->id}/cobrar", ['metodo_pago' => 'efectivo'])->assertNotFound();
    }

    // --- Venta de mostrador --------------------------------------------------------------

    public function test_registra_una_venta_sin_cita(): void
    {
        $this->actingAs($this->recepcion)->post('/caja/ventas', [
            'barbero_id' => $this->barbero->id,
            'servicios' => [$this->corte->id, $this->barba->id],
            'metodo_pago' => 'efectivo',
            'propina' => 2000,
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Venta registrada: $ 37.000.');

        $venta = Venta::firstOrFail();
        $this->assertNull($venta->cita_id);
        $this->assertSame(17500, $venta->comision_monto);
    }

    public function test_la_venta_de_mostrador_exige_barbero_y_servicios(): void
    {
        $this->actingAs($this->dueno)->post('/caja/ventas', ['metodo_pago' => 'efectivo'])
            ->assertSessionHasErrors(['barbero_id', 'servicios']);
    }

    public function test_no_vende_con_un_barbero_de_otra_barberia(): void
    {
        $ajeno = Barbero::factory()->create(['barberia_id' => Barberia::factory()->create()->id]);

        $this->actingAs($this->dueno)->post('/caja/ventas', [
            'barbero_id' => $ajeno->id,
            'servicios' => [$this->corte->id],
            'metodo_pago' => 'efectivo',
        ])->assertNotFound();
    }

    public function test_una_venta_de_mostrador_con_servicios_ajenos_se_rechaza(): void
    {
        $otraBarberia = Barberia::factory()->create();
        $ajeno = Servicio::factory()->create(['barberia_id' => $otraBarberia->id]);

        $this->actingAs($this->dueno)->post('/caja/ventas', [
            'barbero_id' => $this->barbero->id,
            'servicios' => [$ajeno->id],
            'metodo_pago' => 'efectivo',
        ])->assertSessionHasErrors('cobro');

        $this->assertSame(0, Venta::count());
    }

    // --- Anular ---------------------------------------------------------------------------

    public function test_el_dueno_anula_una_venta_con_motivo(): void
    {
        $venta = $this->venta();

        $this->actingAs($this->dueno)->patch("/caja/ventas/{$venta->id}/anular", ['motivo' => 'Cobro duplicado'])->assertSessionHasNoErrors();

        $this->assertTrue($venta->fresh()->estaAnulada());
    }

    public function test_anular_exige_un_motivo(): void
    {
        $venta = $this->venta();

        $this->actingAs($this->dueno)->patch("/caja/ventas/{$venta->id}/anular", ['motivo' => ''])->assertSessionHasErrors('motivo');

        $this->assertFalse($venta->fresh()->estaAnulada());
    }

    public function test_no_se_anula_la_venta_de_otra_barberia(): void
    {
        $ajena = Venta::factory()->create(['barberia_id' => Barberia::factory()->create()->id]);

        $this->actingAs($this->dueno)->patch("/caja/ventas/{$ajena->id}/anular", ['motivo' => 'Intento'])->assertNotFound();
    }

    // --- Cierre de caja ----------------------------------------------------------------------

    public function test_cierra_la_caja_y_explica_la_diferencia(): void
    {
        $this->venta(['total' => 20000, 'subtotal' => 20000, 'metodo_pago' => MetodoPago::Efectivo]);

        $this->actingAs($this->recepcion)->post('/caja/cierre', ['fecha' => '2026-10-12', 'efectivo_contado' => 18000, 'notas' => 'Faltó cambio'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Caja cerrada. Faltan $ 2.000.');

        $cierre = CierreCaja::firstOrFail();
        $this->assertSame(20000, $cierre->efectivo_esperado);
        $this->assertSame(-2000, $cierre->diferencia);
        $this->assertSame($this->recepcion->id, $cierre->user_id);
    }

    public function test_cerrar_dos_veces_el_mismo_dia_da_error(): void
    {
        $this->actingAs($this->dueno)->post('/caja/cierre', ['fecha' => '2026-10-12', 'efectivo_contado' => 0]);

        $this->actingAs($this->dueno)->post('/caja/cierre', ['fecha' => '2026-10-12', 'efectivo_contado' => 0])
            ->assertSessionHasErrors(['cierre' => 'La caja de ese día ya fue cerrada.']);
    }

    public function test_valida_el_cierre(): void
    {
        $this->actingAs($this->dueno)->post('/caja/cierre', ['fecha' => 'hoy', 'efectivo_contado' => -5])
            ->assertSessionHasErrors(['fecha', 'efectivo_contado']);
    }

    public function test_con_la_caja_cerrada_no_se_cobra_hasta_que_el_dueno_la_reabre(): void
    {
        $cita = $this->reservar('10:00');
        $this->actingAs($this->dueno)->post('/caja/cierre', ['fecha' => '2026-10-12', 'efectivo_contado' => 0]);

        $this->actingAs($this->dueno)->post("/caja/citas/{$cita->id}/cobrar", ['metodo_pago' => 'efectivo'])
            ->assertSessionHasErrors('cobro');

        $this->actingAs($this->dueno)->delete('/caja/cierre/'.CierreCaja::firstOrFail()->id)->assertSessionHasNoErrors();
        $this->assertSame(0, CierreCaja::count());

        $this->actingAs($this->dueno)->post("/caja/citas/{$cita->id}/cobrar", ['metodo_pago' => 'efectivo'])->assertSessionHasNoErrors();
        $this->assertSame(1, Venta::count());
    }

    public function test_reabrir_suelta_las_ventas_del_cierre(): void
    {
        $venta = $this->venta(['metodo_pago' => MetodoPago::Efectivo]);
        $this->actingAs($this->dueno)->post('/caja/cierre', ['fecha' => '2026-10-12', 'efectivo_contado' => $venta->total]);
        $this->assertNotNull($venta->fresh()->cierre_caja_id);

        $this->actingAs($this->dueno)->delete('/caja/cierre/'.CierreCaja::firstOrFail()->id);

        $this->assertNull($venta->fresh()->cierre_caja_id);
    }

    public function test_el_cierre_aparece_en_la_pagina(): void
    {
        $this->actingAs($this->dueno)->post('/caja/cierre', ['fecha' => '2026-10-12', 'efectivo_contado' => 0, 'notas' => 'Todo bien']);

        $this->actingAs($this->dueno)->get('/caja?fecha=2026-10-12')->assertInertia(
            fn (Assert $page) => $page->where('cierre.notas', 'Todo bien')->where('cierre.diferencia', 0)
        );
    }

    public function test_no_se_reabre_el_cierre_de_otra_barberia(): void
    {
        $ajeno = CierreCaja::forceCreate(['barberia_id' => Barberia::factory()->create()->id, 'fecha' => '2026-10-12', 'efectivo_esperado' => 0, 'efectivo_contado' => 0, 'diferencia' => 0]);

        $this->actingAs($this->dueno)->delete("/caja/cierre/{$ajeno->id}")->assertNotFound();

        $this->assertSame(1, CierreCaja::withoutGlobalScopes()->count());
    }

    // --- Integración con la agenda y el inicio -----------------------------------------------

    public function test_la_agenda_indica_si_una_cita_se_puede_cobrar_o_ya_esta_cobrada(): void
    {
        $cita = $this->reservar('10:00');

        $this->actingAs($this->dueno)->get('/agenda?fecha=2026-10-12')->assertInertia(
            fn (Assert $page) => $page->where('citas.0.cobrable', true)->where('citas.0.venta', null)
        );

        $this->actingAs($this->dueno)->post("/caja/citas/{$cita->id}/cobrar", ['metodo_pago' => 'tarjeta']);

        $this->actingAs($this->dueno)->get('/agenda?fecha=2026-10-12')->assertInertia(
            fn (Assert $page) => $page->where('citas.0.cobrable', false)->where('citas.0.venta.total', 20000)->where('citas.0.venta.metodo', 'Tarjeta')
        );
    }

    public function test_una_cita_cancelada_no_es_cobrable_en_la_agenda(): void
    {
        $this->reservar('10:00')->update(['estado' => EstadoCita::Cancelada]);

        $this->actingAs($this->dueno)->get('/agenda?fecha=2026-10-12')->assertInertia(
            fn (Assert $page) => $page->where('citas.0.cobrable', false)
        );
    }

    public function test_la_agenda_conserva_el_nombre_del_servicio_aunque_se_elimine(): void
    {
        $this->reservar('10:00');
        $this->corte->delete();

        $this->actingAs($this->dueno)->get('/agenda?fecha=2026-10-12')->assertInertia(
            fn (Assert $page) => $page->where('citas.0.servicios', 'Corte')
        );
    }

    public function test_el_inicio_muestra_lo_cobrado_hoy_sin_contar_anuladas(): void
    {
        $this->venta(['total' => 20000, 'pagada_en' => now()]);
        $this->venta(['total' => 15000, 'pagada_en' => now()]);
        $this->venta(['total' => 99000, 'pagada_en' => now(), 'anulada_en' => now()]);
        $this->venta(['total' => 77000, 'pagada_en' => now()->subDay()]);

        $this->actingAs($this->dueno)->get('/dashboard')->assertInertia(
            fn (Assert $page) => $page->where('hoy.cobrado', 35000)
        );
    }
}
