<?php

namespace Tests\Feature;

use App\Enums\EstadoCita;
use App\Models\Barberia;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Venta;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReglasDeNegocioTest extends TestCase
{
    use RefreshDatabase;

    private Barberia $barberia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barberia = Barberia::factory()->create();
        app(TenantContext::class)->definir($this->barberia);
    }

    private function cita(string $inicio, string $fin, EstadoCita $estado = EstadoCita::Confirmada, ?int $barberoId = null): Cita
    {
        $cita = Cita::factory()->estado($estado)->create([
            'barberia_id' => $this->barberia->id,
            'inicia_en' => Carbon::parse($inicio),
            'termina_en' => Carbon::parse($fin),
            ...($barberoId ? ['barbero_id' => $barberoId] : []),
        ]);

        return $cita;
    }

    public function test_detecta_citas_que_se_cruzan_con_el_rango(): void
    {
        $existente = $this->cita('2026-10-10 10:00', '2026-10-10 10:30');

        $cruza = fn (string $i, string $f) => Cita::solapadas(
            $existente->barbero_id, Carbon::parse($i), Carbon::parse($f)
        )->exists();

        $this->assertTrue($cruza('2026-10-10 10:15', '2026-10-10 10:45'));
        $this->assertTrue($cruza('2026-10-10 09:45', '2026-10-10 10:15'));
        $this->assertTrue($cruza('2026-10-10 09:00', '2026-10-10 11:00'));
    }

    public function test_citas_pegadas_no_se_consideran_cruzadas(): void
    {
        $existente = $this->cita('2026-10-10 10:00', '2026-10-10 10:30');

        $this->assertFalse(Cita::solapadas(
            $existente->barbero_id, Carbon::parse('2026-10-10 10:30'), Carbon::parse('2026-10-10 11:00')
        )->exists());
        $this->assertFalse(Cita::solapadas(
            $existente->barbero_id, Carbon::parse('2026-10-10 09:30'), Carbon::parse('2026-10-10 10:00')
        )->exists());
    }

    public function test_una_cita_cancelada_o_con_inasistencia_libera_el_cupo(): void
    {
        $cancelada = $this->cita('2026-10-10 10:00', '2026-10-10 10:30', EstadoCita::Cancelada);
        $faltó = $this->cita('2026-10-10 10:00', '2026-10-10 10:30', EstadoCita::NoAsistio, $cancelada->barbero_id);

        $this->assertFalse(Cita::solapadas(
            $cancelada->barbero_id, Carbon::parse('2026-10-10 10:00'), Carbon::parse('2026-10-10 10:30')
        )->exists());
        $this->assertSame($cancelada->barbero_id, $faltó->barbero_id);
    }

    public function test_otro_barbero_no_bloquea_el_cupo(): void
    {
        $existente = $this->cita('2026-10-10 10:00', '2026-10-10 10:30');
        $otro = $this->cita('2026-10-10 14:00', '2026-10-10 14:30');

        $this->assertNotSame($existente->barbero_id, $otro->barbero_id);
        $this->assertFalse(Cita::solapadas(
            $otro->barbero_id, Carbon::parse('2026-10-10 10:00'), Carbon::parse('2026-10-10 10:30')
        )->exists());
    }

    public function test_la_comision_se_calcula_sobre_el_subtotal_menos_descuento(): void
    {
        $this->assertSame(12500, Venta::calcularComision(25000, 0, 50));
        $this->assertSame(10000, Venta::calcularComision(25000, 5000, 50));
        $this->assertSame(10500, Venta::calcularComision(35000, 0, '30.00'));
    }

    public function test_la_comision_nunca_es_negativa(): void
    {
        $this->assertSame(0, Venta::calcularComision(10000, 15000, 50));
    }

    public function test_el_telefono_se_normaliza_al_formato_de_whatsapp(): void
    {
        $this->assertSame('573001234567', Cliente::normalizarTelefono('300 123 4567'));
        $this->assertSame('573001234567', Cliente::normalizarTelefono('+57 (300) 123-4567'));
        $this->assertSame('573001234567', Cliente::normalizarTelefono('573001234567'));

        $cliente = Cliente::create(['nombre' => 'Ana', 'telefono' => '300-123-4567']);
        $this->assertSame('573001234567', $cliente->fresh()->telefono);
    }

    public function test_buscar_clientes_por_nombre_o_telefono(): void
    {
        Cliente::factory()->create(['barberia_id' => $this->barberia->id, 'nombre' => 'Carlos Ruiz', 'telefono' => '3101112233']);
        Cliente::factory()->create(['barberia_id' => $this->barberia->id, 'nombre' => 'María López', 'telefono' => '3204445566']);

        $this->assertSame(['Carlos Ruiz'], Cliente::buscar('carlos')->pluck('nombre')->all());
        $this->assertSame(['María López'], Cliente::buscar('320 444')->pluck('nombre')->all());
        $this->assertCount(2, Cliente::buscar(null)->get());
    }
}
