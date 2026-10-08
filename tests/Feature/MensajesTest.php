<?php

namespace Tests\Feature;

use App\Enums\EstadoMensaje;
use App\Enums\Rol;
use App\Models\Barberia;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\MensajeWhatsapp;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\ArmaBarberia;
use Tests\TestCase;

class MensajesTest extends TestCase
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

    private function mensaje(Cita $cita, array $extra = []): MensajeWhatsapp
    {
        return app(TenantContext::class)->ejecutarEn($cita->barberia_id, fn () => MensajeWhatsapp::create([
            'cita_id' => $cita->id,
            'cliente_id' => $cita->cliente_id,
            'tipo' => MensajeWhatsapp::RECORDATORIO,
            'telefono' => '573001234567',
            'estado' => EstadoMensaje::Enviado,
            'enviado_en' => now(),
            ...$extra,
        ]));
    }

    public function test_el_dueno_y_la_recepcion_ven_los_mensajes_pero_el_barbero_no(): void
    {
        $this->get('/mensajes')->assertRedirect('/login');

        $this->actingAs($this->dueno)->get('/mensajes')->assertOk();
        $this->actingAs(User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Recepcion]))->get('/mensajes')->assertOk();
        $this->actingAs(User::factory()->create(['barberia_id' => $this->barberia->id, 'rol' => Rol::Barbero]))->get('/mensajes')->assertForbidden();
    }

    public function test_lista_solo_los_mensajes_de_la_propia_barberia(): void
    {
        $this->mensaje($this->reservar('10:00'), ['estado' => EstadoMensaje::Entregado]);
        $ajena = Cita::factory()->create(['barberia_id' => Barberia::factory()->create()->id]);
        $this->mensaje($ajena);

        $this->actingAs($this->dueno)->get('/mensajes')->assertInertia(
            fn (Assert $page) => $page->component('mensajes/Index')
                ->has('mensajes', 1)
                ->where('mensajes.0.estado', 'entregado')
                ->where('mensajes.0.estado_etiqueta', 'Entregado')
        );
    }

    public function test_avisa_que_esta_en_modo_de_pruebas_mientras_no_se_conecte_whatsapp(): void
    {
        $this->actingAs($this->dueno)->get('/mensajes')->assertInertia(fn (Assert $page) => $page->where('modoPrueba', true));

        config(['whatsapp.driver' => 'cloud']);

        $this->actingAs($this->dueno)->get('/mensajes')->assertInertia(fn (Assert $page) => $page->where('modoPrueba', false));
    }

    public function test_la_agenda_muestra_el_estado_del_recordatorio_de_cada_cita(): void
    {
        $cita = $this->reservar('10:00');
        $this->mensaje($cita, ['estado' => EstadoMensaje::Entregado, 'respuesta' => 'confirmo']);
        $sinMensaje = $this->reservar('11:00', telefono: '3002223344');

        $this->actingAs($this->dueno)->get('/agenda?fecha=2026-10-12')->assertInertia(
            fn (Assert $page) => $page
                ->where('citas.0.recordatorio.estado', 'entregado')
                ->where('citas.0.recordatorio.respuesta', 'confirmo')
                ->where('citas.0.acepta_whatsapp', true)
                ->where('citas.1.recordatorio', null)
        );

        $this->assertNotNull($sinMensaje);
    }

    public function test_la_agenda_indica_cuando_el_cliente_no_autorizo_mensajes(): void
    {
        $cita = $this->reservar('10:00');
        Cliente::withoutGlobalScopes()->whereKey($cita->cliente_id)->update(['acepta_whatsapp' => false]);

        $this->actingAs($this->dueno)->get('/agenda?fecha=2026-10-12')->assertInertia(
            fn (Assert $page) => $page->where('citas.0.acepta_whatsapp', false)
        );
    }
}
