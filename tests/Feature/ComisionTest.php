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

class ComisionTest extends TestCase
{
    use ArmaBarberia, RefreshDatabase;

    private User $dueno;

    private Barbero $segundo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->armarBarberia();
        app(TenantContext::class)->olvidar();

        $this->dueno = User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Dueno]);
        $this->segundo = Barbero::factory()->create(['barberia_id' => $this->barberia->id, 'nombre' => 'Segundo']);

        Carbon::setTestNow('2026-10-20 12:00:00');
    }

    private function venta(Barbero $barbero, string $fecha, int $subtotal, int $descuento, int $comision, int $propina = 0, array $extra = []): Venta
    {
        return Venta::factory()->create([
            'barberia_id' => $this->barberia->id,
            'barbero_id' => $barbero->id,
            'pagada_en' => Carbon::parse($fecha.' 14:00'),
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'propina' => $propina,
            'total' => $subtotal - $descuento + $propina,
            'comision_monto' => $comision,
            ...$extra,
        ]);
    }

    public function test_liquida_las_comisiones_por_barbero_en_el_rango(): void
    {
        // Primer barbero: dos ventas dentro del rango.
        $this->venta($this->barbero, '2026-10-05', 35000, 5000, 15000, propina: 3000);
        $this->venta($this->barbero, '2026-10-06', 20000, 0, 10000);
        // Segundo barbero: una venta.
        $this->venta($this->segundo, '2026-10-07', 15000, 0, 6000, propina: 1000);
        // Fuera de rango, anulada y de otra barbería: no cuentan.
        $this->venta($this->barbero, '2026-09-30', 99000, 0, 49500);
        $this->venta($this->barbero, '2026-10-08', 99000, 0, 49500, extra: ['anulada_en' => now()]);
        Venta::factory()->create(['barberia_id' => Barberia::factory()->create()->id, 'pagada_en' => Carbon::parse('2026-10-05 14:00'), 'subtotal' => 88000, 'comision_monto' => 44000]);

        $this->actingAs($this->dueno)->get('/comisiones?desde=2026-10-01&hasta=2026-10-31')->assertInertia(
            fn (Assert $page) => $page->component('comisiones/Index')
                ->has('filas', 2)
                ->where('filas.0.ventas', 2)
                ->where('filas.0.neto', 50000)        // 30.000 + 20.000
                ->where('filas.0.comision', 25000)
                ->where('filas.0.propinas', 3000)
                ->where('filas.0.a_pagar', 28000)     // comisión + propinas
                ->where('filas.0.barberia', 25000)    // lo que queda para la barbería
                ->where('filas.1.a_pagar', 7000)
                ->where('totales.ventas', 3)
                ->where('totales.neto', 65000)
                ->where('totales.comision', 31000)
                ->where('totales.a_pagar', 35000)
                ->where('totales.barberia', 34000)
        );
    }

    public function test_el_rango_incluye_el_primer_y_el_ultimo_dia_completos(): void
    {
        $this->venta($this->barbero, '2026-10-01', 10000, 0, 5000, extra: ['pagada_en' => Carbon::parse('2026-10-01 00:00:30')]);
        $this->venta($this->barbero, '2026-10-31', 10000, 0, 5000, extra: ['pagada_en' => Carbon::parse('2026-10-31 23:59:30')]);

        $this->actingAs($this->dueno)->get('/comisiones?desde=2026-10-01&hasta=2026-10-31')->assertInertia(
            fn (Assert $page) => $page->where('totales.ventas', 2)
        );
    }

    public function test_por_defecto_muestra_el_mes_en_curso(): void
    {
        $this->venta($this->barbero, '2026-10-02', 10000, 0, 5000);
        $this->venta($this->barbero, '2026-09-28', 10000, 0, 5000);

        $this->actingAs($this->dueno)->get('/comisiones')->assertInertia(
            fn (Assert $page) => $page->where('desde', '2026-10-01')->where('hasta', '2026-10-20')->where('totales.ventas', 1)
        );
    }

    public function test_un_barbero_eliminado_sigue_apareciendo_en_la_liquidacion(): void
    {
        $this->venta($this->segundo, '2026-10-05', 10000, 0, 5000);
        $this->segundo->delete();

        $this->actingAs($this->dueno)->get('/comisiones?desde=2026-10-01&hasta=2026-10-31')->assertInertia(
            fn (Assert $page) => $page->where('filas.0.barbero', 'Segundo')
        );
    }

    public function test_valida_el_rango(): void
    {
        $this->actingAs($this->dueno)->get('/comisiones?desde=2026-10-10&hasta=2026-10-01')->assertSessionHasErrors('hasta');
        $this->actingAs($this->dueno)->get('/comisiones?desde=ayer')->assertSessionHasErrors('desde');
        $this->actingAs($this->dueno)->get('/comisiones?desde=2024-01-01&hasta=2026-10-01')->assertStatus(422);
    }

    public function test_exporta_un_csv_que_excel_abre_bien(): void
    {
        $this->venta($this->barbero, '2026-10-05', 35000, 5000, 15000, propina: 3000);
        $this->venta($this->segundo, '2026-10-07', 15000, 0, 6000);

        $respuesta = $this->actingAs($this->dueno)->get('/comisiones/exportar?desde=2026-10-01&hasta=2026-10-31');

        $respuesta->assertOk();
        $this->assertStringContainsString('text/csv', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('comisiones-2026-10-01-a-2026-10-31.csv', $respuesta->headers->get('Content-Disposition'));

        $csv = $respuesta->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Lleva BOM para que Excel respete las tildes.');

        // Se lee como CSV de verdad (separador ;), no como texto, para no depender de las comillas.
        $filas = array_map(fn ($linea) => str_getcsv($linea, ';'), explode("
", trim(substr($csv, 3))));

        $this->assertSame(['Barbero', 'Ventas', 'Servicios (sin descuentos)', 'Comisión', 'Propinas', 'Total a pagar al barbero', 'Para la barbería'], $filas[0]);
        $this->assertSame([$this->barbero->nombre, '1', '30000', '15000', '3000', '18000', '15000'], $filas[1]);
        $this->assertSame(['TOTAL', '2', '45000', '21000', '3000', '24000', '24000'], end($filas));
    }

    public function test_una_barberia_sin_ventas_da_una_liquidacion_vacia(): void
    {
        $this->actingAs($this->dueno)->get('/comisiones?desde=2026-10-01&hasta=2026-10-31')->assertInertia(
            fn (Assert $page) => $page->has('filas', 0)->where('totales.a_pagar', 0)
        );
    }
}
