<?php

namespace Tests\Feature;

use App\Models\Barberia;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AislamientoBarberiasTest extends TestCase
{
    use RefreshDatabase;

    private function contexto(): TenantContext
    {
        return app(TenantContext::class);
    }

    public function test_sin_barberia_activa_no_se_ve_ningun_dato(): void
    {
        Cliente::factory()->count(3)->create();

        $this->assertSame(0, Cliente::count());
    }

    public function test_cada_barberia_solo_ve_sus_propios_clientes(): void
    {
        $a = Barberia::factory()->create();
        $b = Barberia::factory()->create();
        Cliente::factory()->count(2)->create(['barberia_id' => $a->id]);
        Cliente::factory()->count(5)->create(['barberia_id' => $b->id]);

        $this->contexto()->definir($a);
        $this->assertSame(2, Cliente::count());

        $this->contexto()->definir($b);
        $this->assertSame(5, Cliente::count());
    }

    public function test_un_cliente_de_otra_barberia_no_se_encuentra_por_id(): void
    {
        $a = Barberia::factory()->create();
        $b = Barberia::factory()->create();
        $ajeno = Cliente::factory()->create(['barberia_id' => $b->id]);

        $this->contexto()->definir($a);

        $this->assertNull(Cliente::find($ajeno->id));
    }

    public function test_el_usuario_autenticado_define_la_barberia_activa(): void
    {
        $a = Barberia::factory()->create();
        $b = Barberia::factory()->create();
        Cliente::factory()->count(2)->create(['barberia_id' => $a->id]);
        Cliente::factory()->count(4)->create(['barberia_id' => $b->id]);

        $this->actingAs(User::factory()->create(['barberia_id' => $b->id]));

        $this->assertSame(4, Cliente::count());
    }

    public function test_al_crear_se_asigna_la_barberia_activa(): void
    {
        $a = Barberia::factory()->create();
        $this->contexto()->definir($a);

        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => '300 123 4567']);

        $this->assertSame($a->id, $cliente->barberia_id);
    }

    public function test_barberia_id_no_se_puede_asignar_en_masa(): void
    {
        $a = Barberia::factory()->create();
        $b = Barberia::factory()->create();
        $this->contexto()->definir($a);

        $cliente = Cliente::create([
            'barberia_id' => $b->id,
            'nombre' => 'Intruso',
            'telefono' => '3001112233',
        ]);

        $this->assertSame($a->id, $cliente->barberia_id);
    }

    public function test_ejecutar_en_restaura_el_contexto_anterior(): void
    {
        $a = Barberia::factory()->create();
        $b = Barberia::factory()->create();
        Cliente::factory()->count(1)->create(['barberia_id' => $a->id]);
        Cliente::factory()->count(3)->create(['barberia_id' => $b->id]);

        $this->contexto()->definir($a);

        $dentro = $this->contexto()->ejecutarEn($b, fn () => Cliente::count());

        $this->assertSame(3, $dentro);
        $this->assertSame(1, Cliente::count());
    }

    public function test_el_telefono_puede_repetirse_entre_barberias_pero_no_dentro_de_una(): void
    {
        $a = Barberia::factory()->create();
        $b = Barberia::factory()->create();

        Cliente::factory()->create(['barberia_id' => $a->id, 'telefono' => '3001234567']);
        Cliente::factory()->create(['barberia_id' => $b->id, 'telefono' => '3001234567']);

        $this->expectException(QueryException::class);
        Cliente::factory()->create(['barberia_id' => $a->id, 'telefono' => '573001234567']);
    }

    public function test_las_citas_tambien_estan_aisladas(): void
    {
        $a = Barberia::factory()->create();
        $b = Barberia::factory()->create();
        Cita::factory()->count(2)->create(['barberia_id' => $a->id]);
        Cita::factory()->count(3)->create(['barberia_id' => $b->id]);

        $this->contexto()->definir($a);

        $this->assertSame(2, Cita::count());
    }
}
