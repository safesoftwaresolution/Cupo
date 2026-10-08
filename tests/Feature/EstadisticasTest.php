<?php

namespace Tests\Feature;

use App\Enums\EstadoCita;
use App\Enums\MetodoPago;
use App\Enums\OrigenCita;
use App\Enums\Rol;
use App\Models\Barberia;
use App\Models\Barbero;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\ArmaBarberia;
use Tests\TestCase;

class EstadisticasTest extends TestCase
{
    use ArmaBarberia, RefreshDatabase;

    private User $dueno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->armarBarberia();
        app(TenantContext::class)->olvidar();

        $this->dueno = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Dueno]);

        // Martes 20 de octubre de 2026. Periodo de 30 días: 21-sep a 20-oct. Anterior: 22-ago a 20-sep.
        Carbon::setTestNow('2026-10-20 12:00:00');
    }

    /**
     * @return array<string, mixed>
     */
    private function analitica(string $query = ''): array
    {
        $respuesta = $this->actingAs($this->dueno)->get('/dashboard'.$query)->assertOk();

        return $respuesta->viewData('page')['props']['analitica'];
    }

    /**
     * Una venta de servicios (subtotal) con descuento y propina opcionales.
     *
     * @param  array<string, mixed>  $extra
     */
    private function venta(string $fecha, int $subtotal, int $descuento = 0, array $extra = []): Venta
    {
        return Venta::factory()->create([
            'barberia_id' => $this->barberia->id,
            'barbero_id' => $this->barbero->id,
            'pagada_en' => Carbon::parse("$fecha 14:00"),
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'propina' => 0,
            'total' => $subtotal - $descuento,
            'comision_monto' => (int) round(($subtotal - $descuento) * 0.5),
            'metodo_pago' => MetodoPago::Efectivo,
            ...$extra,
        ]);
    }

    private function cita(string $inicio, int $minutos = 30, EstadoCita $estado = EstadoCita::Completada, array $extra = []): Cita
    {
        $inicia = Carbon::parse($inicio);

        return Cita::factory()->create([
            'barberia_id' => $this->barberia->id,
            'barbero_id' => $this->barbero->id,
            'inicia_en' => $inicia,
            'termina_en' => $inicia->copy()->addMinutes($minutos),
            'duracion_total_minutos' => $minutos,
            'estado' => $estado,
            'origen' => OrigenCita::Manual,
            ...$extra,
        ]);
    }

    // --- Acceso ---------------------------------------------------------------------------

    public function test_solo_el_dueno_recibe_las_estadisticas(): void
    {
        $recepcion = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Recepcion]);

        $this->actingAs($recepcion)->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('analitica', null));

        $this->assertIsArray($this->analitica());
    }

    public function test_el_periodo_se_elige_con_el_parametro_rango(): void
    {
        foreach ([
            ['', '2026-09-21', 30],
            ['?rango=7', '2026-10-14', 7],
            ['?rango=90', '2026-07-23', 90],
            ['?rango=mes', '2026-10-01', 20],
            ['?rango=cualquier-cosa', '2026-09-21', 30],
        ] as [$query, $desde, $dias]) {
            $periodo = $this->analitica($query)['periodo'];

            $this->assertSame($desde, $periodo['desde'], "Desde para {$query}");
            $this->assertSame('2026-10-20', $periodo['hasta']);
            $this->assertSame($dias, $periodo['dias'], "Días para {$query}");
        }
    }

    // --- Indicadores y comparación ---------------------------------------------------------

    public function test_calcula_ventas_atenciones_y_ticket_contra_el_periodo_anterior(): void
    {
        // Periodo actual: 30.000 + 20.000 (con 5.000 de descuento) = 45.000 de servicios en 2 atenciones.
        $this->venta('2026-10-05', 30000);
        $this->venta('2026-10-10', 20000, descuento: 5000);
        // Periodo anterior: 30.000 en 1 atención.
        $this->venta('2026-09-10', 30000);

        $kpis = $this->analitica()['kpis'];

        $this->assertSame(45000, $kpis['ventas']['valor']);
        $this->assertSame(30000, $kpis['ventas']['anterior']);
        $this->assertSame(50.0, $kpis['ventas']['cambio']);
        $this->assertSame(2, $kpis['atenciones']['valor']);
        $this->assertSame(1, $kpis['atenciones']['anterior']);
        $this->assertSame(100.0, $kpis['atenciones']['cambio']);
        $this->assertSame(22500, $kpis['ticket']['valor']);
        $this->assertSame(30000, $kpis['ticket']['anterior']);
        $this->assertSame(-25.0, $kpis['ticket']['cambio']);
    }

    public function test_las_propinas_no_cuentan_como_ventas(): void
    {
        $this->venta('2026-10-05', 20000, extra: ['propina' => 5000, 'total' => 25000]);

        $this->assertSame(20000, $this->analitica()['kpis']['ventas']['valor']);
    }

    public function test_sin_periodo_anterior_no_inventa_un_porcentaje(): void
    {
        $this->venta('2026-10-05', 30000);

        $kpis = $this->analitica()['kpis'];

        $this->assertNull($kpis['ventas']['cambio']);
        $this->assertNull($kpis['atenciones']['cambio']);
    }

    public function test_las_ventas_anuladas_y_las_de_otras_barberias_no_cuentan(): void
    {
        $this->venta('2026-10-05', 30000);
        $this->venta('2026-10-06', 99000, extra: ['anulada_en' => now()]);
        Venta::factory()->create(['barberia_id' => Barberia::factory()->create()->id, 'pagada_en' => Carbon::parse('2026-10-05 14:00'), 'subtotal' => 77000, 'descuento' => 0, 'total' => 77000]);

        $analitica = $this->analitica();

        $this->assertSame(30000, $analitica['kpis']['ventas']['valor']);
        $this->assertSame(1, $analitica['kpis']['atenciones']['valor']);
    }

    public function test_sin_comisiones_no_hay_indicador_de_ganancia_para_la_barberia(): void
    {
        $this->venta('2026-10-05', 30000);

        $this->assertSame(15000, $this->analitica()['kpis']['barberia']['valor'], 'Servicios 30.000 menos comisión 15.000.');

        $this->barberia->update(['maneja_comisiones' => false]);
        $this->dueno->unsetRelation('barberia');

        $analitica = $this->analitica();
        $this->assertArrayNotHasKey('barberia', $analitica['kpis']);
        $this->assertSame(0, $analitica['barberos'][0]['comision']);
    }

    // --- Ventas por día ----------------------------------------------------------------------

    public function test_ventas_por_dia_rellena_con_ceros_y_alinea_el_periodo_anterior(): void
    {
        $this->venta('2026-10-20', 30000);          // último día del periodo actual
        $this->venta('2026-09-21', 10000);          // primer día del periodo actual
        $this->venta('2026-09-20', 8000);           // último día del periodo anterior (alinea con el día 30)
        $this->venta('2026-08-22', 5000);           // primer día del periodo anterior (alinea con el día 1)
        $this->venta('2026-08-21', 99000);          // fuera de todo

        $dias = $this->analitica()['ventas_por_dia'];

        $this->assertCount(30, $dias);
        $this->assertSame('2026-09-21', $dias[0]['fecha']);
        $this->assertSame(10000, $dias[0]['valor']);
        $this->assertSame(5000, $dias[0]['anterior']);
        $this->assertSame('2026-10-20', $dias[29]['fecha']);
        $this->assertSame(30000, $dias[29]['valor']);
        $this->assertSame(8000, $dias[29]['anterior']);
        $this->assertSame(0, $dias[10]['valor']);
        $this->assertSame(40000, array_sum(array_column($dias, 'valor')));
    }

    // --- Rankings --------------------------------------------------------------------------------

    public function test_los_servicios_mas_vendidos_se_ordenan_por_ingresos_y_no_pasan_de_seis(): void
    {
        $uno = $this->venta('2026-10-05', 0);
        $dos = $this->venta('2026-10-06', 0);
        $anulada = $this->venta('2026-10-07', 0, extra: ['anulada_en' => now()]);

        VentaItem::create(['venta_id' => $uno->id, 'descripcion' => 'Corte', 'cantidad' => 1, 'precio_unitario' => 20000]);
        VentaItem::create(['venta_id' => $dos->id, 'descripcion' => 'Corte', 'cantidad' => 1, 'precio_unitario' => 20000]);
        VentaItem::create(['venta_id' => $dos->id, 'descripcion' => 'Barba', 'cantidad' => 1, 'precio_unitario' => 15000]);
        VentaItem::create(['venta_id' => $anulada->id, 'descripcion' => 'Fade', 'cantidad' => 5, 'precio_unitario' => 99999]);
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $i => $letra) {
            VentaItem::create(['venta_id' => $uno->id, 'descripcion' => "Extra {$letra}", 'cantidad' => 1, 'precio_unitario' => 100 + $i]);
        }

        $servicios = $this->analitica()['servicios'];

        $this->assertCount(6, $servicios);
        $this->assertSame(['nombre' => 'Corte', 'cantidad' => 2, 'ingresos' => 40000], $servicios[0]);
        $this->assertSame('Barba', $servicios[1]['nombre']);
        $this->assertNotContains('Fade', array_column($servicios, 'nombre'), 'Las ventas anuladas no cuentan.');
    }

    public function test_ventas_por_barbero_incluye_a_los_eliminados_y_ordena_de_mayor_a_menor(): void
    {
        $segundo = Barbero::factory()->create(['barberia_id' => $this->barberia->id, 'nombre' => 'Segundo']);
        $this->venta('2026-10-05', 10000);
        $this->venta('2026-10-06', 50000, extra: ['barbero_id' => $segundo->id]);
        $segundo->delete();

        $barberos = $this->analitica()['barberos'];

        $this->assertSame(['Segundo', $this->barbero->nombre], array_column($barberos, 'nombre'));
        $this->assertSame([50000, 10000], array_column($barberos, 'ventas'));
        $this->assertSame(25000, $barberos[0]['comision']);
    }

    public function test_los_metodos_de_pago_se_ordenan_por_monto(): void
    {
        $this->venta('2026-10-05', 10000, extra: ['metodo_pago' => MetodoPago::Nequi]);
        $this->venta('2026-10-06', 30000, extra: ['metodo_pago' => MetodoPago::Efectivo]);
        $this->venta('2026-10-07', 5000, extra: ['metodo_pago' => MetodoPago::Efectivo]);

        $metodos = $this->analitica()['metodos'];

        $this->assertSame([['etiqueta' => 'Efectivo', 'total' => 35000, 'cantidad' => 2], ['etiqueta' => 'Nequi', 'total' => 10000, 'cantidad' => 1]], $metodos);
    }

    public function test_el_origen_de_las_citas_separa_enlace_whatsapp_y_personal_sin_contar_canceladas(): void
    {
        $this->cita('2026-10-05 10:00', extra: ['origen' => OrigenCita::Web]);
        $this->cita('2026-10-05 11:00', extra: ['origen' => OrigenCita::Web]);
        $this->cita('2026-10-05 12:00', extra: ['origen' => OrigenCita::Whatsapp]);
        $this->cita('2026-10-06 10:00');
        $this->cita('2026-10-06 11:00', estado: EstadoCita::Cancelada, extra: ['origen' => OrigenCita::Web]);

        $this->assertSame([
            ['etiqueta' => 'Enlace de reservas', 'cantidad' => 2],
            ['etiqueta' => 'WhatsApp', 'cantidad' => 1],
            ['etiqueta' => 'Agendada por el personal', 'cantidad' => 1],
        ], $this->analitica()['origen']);
    }

    // --- Asistencia y ocupación -----------------------------------------------------------------------

    public function test_la_inasistencia_es_el_porcentaje_de_citas_resueltas_en_que_no_llegaron(): void
    {
        $this->cita('2026-10-05 10:00');
        $this->cita('2026-10-05 11:00');
        $this->cita('2026-10-05 12:00');
        $this->cita('2026-10-05 13:00', estado: EstadoCita::NoAsistio);
        $this->cita('2026-10-05 14:00', estado: EstadoCita::Cancelada);  // no cuenta: avisó
        $this->cita('2026-10-05 15:00', estado: EstadoCita::Pendiente);  // no cuenta: sin resolver

        $inasistencia = $this->analitica()['kpis']['inasistencia'];

        $this->assertSame(25.0, $inasistencia['valor']);
        $this->assertSame(1, $inasistencia['casos']);
    }

    public function test_sin_citas_resueltas_la_inasistencia_no_se_inventa(): void
    {
        $this->assertNull($this->analitica()['kpis']['inasistencia']['valor']);
    }

    public function test_la_ocupacion_compara_minutos_agendados_con_minutos_de_horario(): void
    {
        // En los últimos 7 días (14 al 20 de octubre) solo hay un lunes (el 19) y el barbero trabaja 9 h = 540 min.
        $this->cita('2026-10-19 09:00', 270);
        $this->cita('2026-10-19 14:00', 100, EstadoCita::Cancelada); // las canceladas no ocupan

        $ocupacion = $this->analitica('?rango=7')['kpis']['ocupacion'];

        $this->assertSame(50.0, $ocupacion['valor']);
    }

    public function test_la_ocupacion_nunca_pasa_de_100(): void
    {
        $this->cita('2026-10-19 09:00', 900);

        $this->assertSame(100.0, $this->analitica('?rango=7')['kpis']['ocupacion']['valor']);
    }

    public function test_sin_horarios_no_hay_ocupacion(): void
    {
        \App\Models\Horario::withoutGlobalScopes()->delete();

        $this->assertNull($this->analitica()['kpis']['ocupacion']['valor']);
    }

    // --- Demanda ----------------------------------------------------------------------------------------

    public function test_el_mapa_de_demanda_cuenta_citas_por_dia_de_la_semana_y_hora(): void
    {
        $this->cita('2026-10-05 10:00'); // lunes 10 h
        $this->cita('2026-10-12 10:30'); // lunes 10 h
        $this->cita('2026-10-12 17:00'); // lunes 17 h
        $this->cita('2026-10-10 17:00'); // sábado 17 h
        $this->cita('2026-10-12 18:00', estado: EstadoCita::Cancelada); // no cuenta

        $demanda = $this->analitica()['demanda'];

        $hora = fn (int $h) => $h - $demanda['horas'][0];
        $this->assertSame(8, $demanda['horas'][0]);
        $this->assertSame(19, end($demanda['horas']));
        $this->assertSame(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'], $demanda['dias']);
        $this->assertSame(2, $demanda['celdas'][0][$hora(10)]);
        $this->assertSame(1, $demanda['celdas'][0][$hora(17)]);
        $this->assertSame(1, $demanda['celdas'][5][$hora(17)]);
        $this->assertSame(0, $demanda['celdas'][1][$hora(10)]);
        $this->assertSame(2, $demanda['maximo']);
        $this->assertSame(4, $demanda['total']);
    }

    public function test_el_mapa_de_demanda_se_amplia_si_hay_citas_fuera_del_horario_normal(): void
    {
        $this->cita('2026-10-05 06:30');
        $this->cita('2026-10-05 21:00');

        $demanda = $this->analitica()['demanda'];

        $this->assertSame(6, $demanda['horas'][0]);
        $this->assertSame(21, end($demanda['horas']));
    }

    // --- Clientes -------------------------------------------------------------------------------------------

    public function test_separa_clientes_nuevos_de_recurrentes(): void
    {
        $recurrente = Cliente::factory()->create(['barberia_id' => $this->barberia->id]);
        $nuevo = Cliente::factory()->create(['barberia_id' => $this->barberia->id]);

        $this->venta('2026-08-01', 20000, extra: ['cliente_id' => $recurrente->id]); // vino antes del periodo
        $this->venta('2026-10-05', 20000, extra: ['cliente_id' => $recurrente->id]);
        $this->venta('2026-10-06', 20000, extra: ['cliente_id' => $nuevo->id]);
        $this->venta('2026-10-07', 20000, extra: ['cliente_id' => $nuevo->id]); // dos visitas en el periodo: sigue siendo uno
        $this->venta('2026-10-08', 20000, extra: ['cliente_id' => null]);        // mostrador sin cliente

        $this->assertSame(['nuevos' => 1, 'recurrentes' => 1, 'sin_registro' => 1], $this->analitica()['clientes']);
    }

    // --- Clientes en riesgo -------------------------------------------------------------------------------------

    private function habitual(string $nombre, array $fechas, array $extra = []): Cliente
    {
        $cliente = Cliente::factory()->create(['barberia_id' => $this->barberia->id, 'nombre' => $nombre, ...$extra]);

        foreach ($fechas as $fecha) {
            $this->venta($fecha, 20000, extra: ['cliente_id' => $cliente->id]);
        }

        return $cliente;
    }

    public function test_lista_a_los_habituales_que_dejaron_de_venir(): void
    {
        $this->habitual('Carlos Perdido', ['2026-06-01', '2026-07-10', '2026-08-21']); // última: hace 60 días

        $enRiesgo = $this->analitica()['en_riesgo'];

        $this->assertCount(1, $enRiesgo);
        $this->assertSame('Carlos Perdido', $enRiesgo[0]['nombre']);
        $this->assertSame(3, $enRiesgo[0]['visitas']);
        $this->assertSame('2026-08-21', $enRiesgo[0]['ultima']);
        $this->assertSame(60, $enRiesgo[0]['dias']);
    }

    public function test_no_marca_en_riesgo_a_quien_no_corresponde(): void
    {
        $this->habitual('Una sola visita', ['2026-07-01']);                        // no es habitual
        $this->habitual('Vino hace poco', ['2026-08-01', '2026-10-10']);           // última visita reciente
        $this->habitual('Sin autorización', ['2026-06-01', '2026-07-01'], ['acepta_whatsapp' => false]);
        $conCita = $this->habitual('Ya tiene cita', ['2026-06-01', '2026-07-01']);
        $this->cita('2026-10-25 10:00', estado: EstadoCita::Confirmada, extra: ['cliente_id' => $conCita->id]);

        $ajena = Cliente::factory()->create(['barberia_id' => Barberia::factory()->create()->id]);
        Venta::factory()->count(2)->create(['barberia_id' => $ajena->barberia_id, 'cliente_id' => $ajena->id, 'pagada_en' => Carbon::parse('2026-06-01')]);

        $this->assertSame([], $this->analitica()['en_riesgo']);
    }

    public function test_las_ventas_anuladas_no_cuentan_como_visitas(): void
    {
        $cliente = Cliente::factory()->create(['barberia_id' => $this->barberia->id]);
        $this->venta('2026-06-01', 20000, extra: ['cliente_id' => $cliente->id]);
        $this->venta('2026-06-15', 20000, extra: ['cliente_id' => $cliente->id, 'anulada_en' => now()]);

        $this->assertSame([], $this->analitica()['en_riesgo'], 'Con una sola visita real no es habitual.');
    }

    public function test_los_mas_fieles_van_primero_y_la_lista_se_limita_a_ocho(): void
    {
        foreach (range(1, 10) as $i) {
            $this->habitual("Cliente {$i}", array_fill(0, 1 + $i, '2026-07-01'));
        }

        $enRiesgo = $this->analitica()['en_riesgo'];

        $this->assertCount(8, $enRiesgo);
        $this->assertSame('Cliente 10', $enRiesgo[0]['nombre']);
    }
}
