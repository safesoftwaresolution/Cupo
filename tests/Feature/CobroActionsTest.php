<?php

namespace Tests\Feature;

use App\Actions\AnularVenta;
use App\Actions\CerrarCaja;
use App\Actions\CobrarCita;
use App\Actions\VenderServicios;
use App\Enums\EstadoCita;
use App\Enums\MetodoPago;
use App\Enums\Rol;
use App\Exceptions\CobroNoPermitido;
use App\Models\CierreCaja;
use App\Models\Servicio;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\ArmaBarberia;
use Tests\TestCase;

class CobroActionsTest extends TestCase
{
    use ArmaBarberia, RefreshDatabase;

    private User $dueno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->armarBarberia();
        $this->dueno = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Dueno]);
    }

    /** Se cobra el lunes al mediodía, cuando ya pasó la cita de las 10:00. */
    private function lunesAlMediodia(): void
    {
        Carbon::setTestNow('2026-10-12 12:00:00');
    }

    private function cobrar(int $citaId, int $descuento = 0, int $propina = 0, MetodoPago $metodo = MetodoPago::Efectivo): Venta
    {
        return app(CobrarCita::class)(\App\Models\Cita::findOrFail($citaId), $descuento, $propina, $metodo, $this->dueno);
    }

    // --- Cobrar una cita ------------------------------------------------------

    public function test_cobra_una_cita_con_descuento_y_propina(): void
    {
        $cita = $this->reservar('10:00', [$this->corte->id, $this->barba->id]); // 20.000 + 15.000
        $this->lunesAlMediodia();

        $venta = $this->cobrar($cita->id, descuento: 5000, propina: 3000, metodo: MetodoPago::Nequi);

        $this->assertSame(35000, $venta->subtotal);
        $this->assertSame(5000, $venta->descuento);
        $this->assertSame(3000, $venta->propina);
        $this->assertSame(33000, $venta->total, 'Total = 35.000 - 5.000 + 3.000');
        $this->assertSame(15000, $venta->comision_monto, 'Comisión 50% sobre (35.000 - 5.000); la propina no cuenta.');
        $this->assertSame(MetodoPago::Nequi, $venta->metodo_pago);
        $this->assertSame($this->dueno->id, $venta->user_id);
        $this->assertSame($cita->id, $venta->cita_id);
        $this->assertSame($cita->cliente_id, $venta->cliente_id);
        $this->assertSame(['Corte', 'Barba'], $venta->items->pluck('descripcion')->all());
        $this->assertSame(EstadoCita::Completada, $cita->fresh()->estado);
    }

    public function test_el_porcentaje_de_comision_queda_guardado_en_la_venta(): void
    {
        $cita = $this->reservar();
        $this->lunesAlMediodia();
        $venta = $this->cobrar($cita->id);

        $this->barbero->update(['comision_porcentaje' => 70]);

        $venta->refresh();
        $this->assertSame('50.00', $venta->comision_porcentaje);
        $this->assertSame(10000, $venta->comision_monto, 'Cambiar la comisión después no altera ventas pasadas.');
    }

    public function test_la_comision_se_redondea_al_peso(): void
    {
        $this->corte->update(['precio' => 10001]);
        $cita = $this->reservar();
        $this->lunesAlMediodia();

        $venta = $this->cobrar($cita->id);

        $this->assertSame(5001, $venta->comision_monto, '10.001 × 50% = 5.000,5 → 5.001');
    }

    public function test_un_descuento_igual_al_total_deja_solo_la_propina(): void
    {
        $cita = $this->reservar();
        $this->lunesAlMediodia();

        $venta = $this->cobrar($cita->id, descuento: 20000, propina: 2000);

        $this->assertSame(2000, $venta->total);
        $this->assertSame(0, $venta->comision_monto);
    }

    public function test_el_descuento_no_puede_superar_el_valor_de_los_servicios(): void
    {
        $cita = $this->reservar();
        $this->lunesAlMediodia();

        try {
            $this->cobrar($cita->id, descuento: 20001);
            $this->fail('Debió rechazar el descuento.');
        } catch (CobroNoPermitido $e) {
            $this->assertStringContainsString('descuento', $e->getMessage());
        }

        $this->assertSame(0, Venta::count());
        $this->assertSame(EstadoCita::Pendiente, $cita->fresh()->estado, 'Si el cobro falla, la cita no cambia.');
    }

    public function test_una_cita_no_se_cobra_dos_veces(): void
    {
        $cita = $this->reservar();
        $this->lunesAlMediodia();
        $this->cobrar($cita->id);

        $this->expectExceptionObject(CobroNoPermitido::citaYaCobrada());
        $this->cobrar($cita->id);
    }

    public function test_una_cita_cancelada_o_con_inasistencia_no_se_cobra(): void
    {
        foreach ([EstadoCita::Cancelada, EstadoCita::NoAsistio] as $estado) {
            $cita = $this->reservar($estado === EstadoCita::Cancelada ? '10:00' : '11:00', telefono: $estado === EstadoCita::Cancelada ? '3001111111' : '3002222222');
            $cita->update(['estado' => $estado]);
            $this->lunesAlMediodia();

            try {
                $this->cobrar($cita->id);
                $this->fail("Una cita {$estado->value} no debe cobrarse.");
            } catch (CobroNoPermitido $e) {
                $this->assertStringContainsString('no se puede cobrar', $e->getMessage());
            }
        }

        $this->assertSame(0, Venta::count());
    }

    public function test_una_cita_marcada_completada_a_mano_todavia_se_puede_cobrar(): void
    {
        $cita = $this->reservar();
        $cita->update(['estado' => EstadoCita::Completada]);
        $this->lunesAlMediodia();

        $venta = $this->cobrar($cita->id);

        $this->assertSame(20000, $venta->total);
    }

    public function test_cobra_aunque_el_servicio_se_haya_eliminado_despues_de_reservar(): void
    {
        $cita = $this->reservar();
        $this->corte->delete();
        $this->lunesAlMediodia();

        $venta = $this->cobrar($cita->id);

        $this->assertSame(20000, $venta->total);
        $this->assertSame('Corte', $venta->items->first()->descripcion);
    }

    // --- Venta de mostrador ------------------------------------------------------

    public function test_vende_servicios_a_quien_llega_sin_cita(): void
    {
        $this->barbero->servicios()->updateExistingPivot($this->barba->id, ['precio_especial' => 12000]);
        $this->lunesAlMediodia();

        $venta = app(VenderServicios::class)($this->barbero, [$this->corte->id, $this->barba->id], 0, 1000, MetodoPago::Efectivo, $this->dueno);

        $this->assertNull($venta->cita_id);
        $this->assertSame(32000, $venta->subtotal, 'Corte 20.000 + barba con precio especial 12.000');
        $this->assertSame(33000, $venta->total);
        $this->assertSame(16000, $venta->comision_monto);
    }

    public function test_no_vende_servicios_que_el_barbero_no_hace(): void
    {
        $otro = Servicio::factory()->create(['barberia_id' => $this->barberia->id]);

        $this->expectExceptionObject(CobroNoPermitido::servicioNoOfrecido());
        app(VenderServicios::class)($this->barbero, [$otro->id], 0, 0, MetodoPago::Efectivo, $this->dueno);
    }

    // --- Anular -------------------------------------------------------------------

    public function test_anular_una_venta_la_deja_fuera_de_las_cuentas_y_libera_la_cita(): void
    {
        $cita = $this->reservar();
        $this->lunesAlMediodia();
        $venta = $this->cobrar($cita->id);

        app(AnularVenta::class)($venta, 'Cobro duplicado', $this->dueno);

        $venta->refresh();
        $this->assertTrue($venta->estaAnulada());
        $this->assertSame('Cobro duplicado', $venta->motivo_anulacion);
        $this->assertSame($this->dueno->id, $venta->anulada_por);
        $this->assertSame(0, Venta::vigentes()->count());
        $this->assertSame(EstadoCita::Confirmada, $cita->fresh()->estado);
    }

    public function test_una_cita_se_puede_cobrar_de_nuevo_tras_anular(): void
    {
        $cita = $this->reservar();
        $this->lunesAlMediodia();
        $primera = $this->cobrar($cita->id, metodo: MetodoPago::Efectivo);
        app(AnularVenta::class)($primera, 'Método equivocado', $this->dueno);

        $segunda = $this->cobrar($cita->id, metodo: MetodoPago::Tarjeta);

        $this->assertSame(2, Venta::count());
        $this->assertSame([$segunda->id], Venta::vigentes()->pluck('id')->all());
        $this->assertSame(EstadoCita::Completada, $cita->fresh()->estado);
    }

    public function test_la_base_de_datos_impide_dos_ventas_vigentes_para_la_misma_cita(): void
    {
        $cita = $this->reservar();
        $this->lunesAlMediodia();
        $this->cobrar($cita->id);

        $this->expectException(QueryException::class);
        Venta::factory()->create(['barberia_id' => $this->barberia->id, 'barbero_id' => $this->barbero->id, 'cita_id' => $cita->id]);
    }

    public function test_no_se_anula_dos_veces(): void
    {
        $this->lunesAlMediodia();
        $venta = app(VenderServicios::class)($this->barbero, [$this->corte->id], 0, 0, MetodoPago::Efectivo, $this->dueno);
        app(AnularVenta::class)($venta, 'Error', $this->dueno);

        $this->expectExceptionObject(CobroNoPermitido::ventaYaAnulada());
        app(AnularVenta::class)($venta, 'Otra vez', $this->dueno);
    }

    // --- Cierre de caja -----------------------------------------------------------

    private function venderHoy(MetodoPago $metodo, int $propina = 0, ?int $servicioId = null): Venta
    {
        return app(VenderServicios::class)($this->barbero, [$servicioId ?? $this->corte->id], 0, $propina, $metodo, $this->dueno);
    }

    public function test_el_efectivo_esperado_suma_solo_ventas_vigentes_en_efectivo(): void
    {
        $this->lunesAlMediodia();
        $this->venderHoy(MetodoPago::Efectivo, propina: 2000);   // 22.000 en efectivo
        $this->venderHoy(MetodoPago::Efectivo, servicioId: $this->barba->id); // 15.000
        $this->venderHoy(MetodoPago::Nequi);                      // no es efectivo
        $anulada = $this->venderHoy(MetodoPago::Efectivo);        // se anula, no cuenta
        app(AnularVenta::class)($anulada, 'Error', $this->dueno);

        $cierre = app(CerrarCaja::class)(now(), 37000, null, $this->dueno);

        $this->assertSame(37000, $cierre->efectivo_esperado);
        $this->assertSame(0, $cierre->diferencia);
    }

    public function test_la_diferencia_es_positiva_si_sobra_y_negativa_si_falta(): void
    {
        $this->lunesAlMediodia();
        $this->venderHoy(MetodoPago::Efectivo); // esperado 20.000

        $this->assertSame(-5000, app(CerrarCaja::class)(now(), 15000, 'Faltó una propina', $this->dueno)->diferencia);

        CierreCaja::query()->delete();
        $this->assertSame(3000, app(CerrarCaja::class)(now(), 23000, null, $this->dueno)->diferencia);
    }

    public function test_el_cierre_liga_las_ventas_del_dia_y_no_las_de_otros_dias(): void
    {
        Carbon::setTestNow('2026-10-11 15:00:00');
        $ayer = $this->venderHoy(MetodoPago::Efectivo);

        Carbon::setTestNow('2026-10-12 15:00:00');
        $hoy = $this->venderHoy(MetodoPago::Efectivo);

        $cierre = app(CerrarCaja::class)(now(), 20000, null, $this->dueno);

        $this->assertSame($cierre->id, $hoy->fresh()->cierre_caja_id);
        $this->assertNull($ayer->fresh()->cierre_caja_id);
        $this->assertSame(20000, $cierre->efectivo_esperado, 'El efectivo de ayer no entra en el cierre de hoy.');
    }

    public function test_un_dia_no_se_cierra_dos_veces(): void
    {
        $this->lunesAlMediodia();
        app(CerrarCaja::class)(now(), 0, null, $this->dueno);

        $this->expectExceptionObject(CobroNoPermitido::cierreYaExiste());
        app(CerrarCaja::class)(now(), 0, null, $this->dueno);
    }

    public function test_no_se_cierra_la_caja_de_un_dia_futuro(): void
    {
        $this->expectExceptionObject(CobroNoPermitido::fechaFutura());
        app(CerrarCaja::class)(now()->addDay(), 0, null, $this->dueno);
    }

    public function test_con_la_caja_cerrada_no_se_cobra_ni_se_anula(): void
    {
        $this->lunesAlMediodia();
        $venta = $this->venderHoy(MetodoPago::Efectivo);
        app(CerrarCaja::class)(now(), 20000, null, $this->dueno);

        try {
            $this->venderHoy(MetodoPago::Efectivo);
            $this->fail('No debe permitir cobrar con la caja cerrada.');
        } catch (CobroNoPermitido $e) {
            $this->assertStringContainsString('cerrada', $e->getMessage());
        }

        $this->expectExceptionObject(CobroNoPermitido::cajaCerrada());
        app(AnularVenta::class)($venta, 'Tarde', $this->dueno);
    }
}
