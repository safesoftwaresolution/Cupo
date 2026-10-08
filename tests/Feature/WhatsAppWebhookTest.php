<?php

namespace Tests\Feature;

use App\Enums\EstadoCita;
use App\Enums\EstadoMensaje;
use App\Models\Barberia;
use App\Models\Cita;
use App\Models\MensajeWhatsapp;
use App\Support\Disponibilidad;
use App\Support\TenantContext;
use App\Support\WhatsApp\WhatsAppCliente;
use App\Support\WhatsApp\WhatsAppError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Concerns\ArmaBarberia;
use Tests\Support\FakeWhatsApp;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use ArmaBarberia, RefreshDatabase;

    private const SECRETO = 'secreto-de-prueba';

    private FakeWhatsApp $whatsapp;

    private Cita $cita;

    private MensajeWhatsapp $mensaje;

    protected function setUp(): void
    {
        parent::setUp();

        $this->armarBarberia();
        config(['whatsapp.app_secret' => self::SECRETO, 'whatsapp.verify_token' => 'palabra-secreta']);

        $this->whatsapp = new FakeWhatsApp;
        $this->app->instance(WhatsAppCliente::class, $this->whatsapp);

        Carbon::setTestNow('2026-10-11 12:00:00');
        $this->cita = $this->reservar('10:00'); // el cliente es 573001234567
        $this->mensaje = app(TenantContext::class)->ejecutarEn($this->barberia, fn () => MensajeWhatsapp::create([
            'cita_id' => $this->cita->id,
            'cliente_id' => $this->cita->cliente_id,
            'tipo' => MensajeWhatsapp::RECORDATORIO,
            'telefono' => '573001234567',
            'estado' => EstadoMensaje::Enviado,
            'wa_message_id' => 'wamid.ENVIADO',
        ]));

        // El webhook no tiene sesión ni barbería: debe encontrarla él solo.
        app(TenantContext::class)->olvidar();
    }

    /**
     * Envía una petición al webhook con el cuerpo exacto firmado, como lo hace Meta.
     *
     * @param  array<string, mixed>  $payload
     */
    private function webhook(array $payload, ?string $firma = null): TestResponse
    {
        $cuerpo = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $firma ??= 'sha256='.hash_hmac('sha256', $cuerpo, self::SECRETO);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $firma,
        ], $cuerpo);
    }

    /**
     * @param  list<array<string, mixed>>  $mensajes
     * @param  list<array<string, mixed>>  $estados
     * @return array<string, mixed>
     */
    private function cambio(array $mensajes = [], array $estados = []): array
    {
        return ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => array_filter([
            'messages' => $mensajes,
            'statuses' => $estados,
        ])]]]]];
    }

    private function boton(string $payload, string $desde = '573001234567'): array
    {
        return ['from' => $desde, 'id' => 'wamid.IN'.uniqid(), 'type' => 'button', 'button' => ['payload' => $payload, 'text' => 'Confirmar']];
    }

    private function citaActual(): Cita
    {
        return Cita::withoutGlobalScopes()->findOrFail($this->cita->id);
    }

    private function mensajeActual(): MensajeWhatsapp
    {
        return MensajeWhatsapp::withoutGlobalScopes()->findOrFail($this->mensaje->id);
    }

    // --- Verificación y firma -----------------------------------------------------------

    public function test_responde_el_desafio_de_verificacion_de_meta(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=palabra-secreta&hub.challenge=12345')
            ->assertOk()
            ->assertSee('12345', false);
    }

    public function test_rechaza_la_verificacion_con_una_palabra_equivocada_o_sin_configurar(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=otra&hub.challenge=1')->assertForbidden();

        config(['whatsapp.verify_token' => '']);
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=&hub.challenge=1')->assertForbidden();
    }

    public function test_rechaza_peticiones_con_firma_invalida_o_ausente(): void
    {
        $payload = $this->cambio([$this->boton("confirmar:{$this->cita->codigo}")]);

        $this->webhook($payload, 'sha256=falsa')->assertForbidden();
        $this->webhook($payload, '')->assertForbidden();
        $this->postJson('/webhooks/whatsapp', $payload)->assertForbidden();

        $this->assertSame(EstadoCita::Pendiente, $this->citaActual()->estado, 'Una petición sin firma no puede confirmar nada.');
    }

    public function test_una_firma_de_otro_cuerpo_no_vale(): void
    {
        $firmaDeOtro = 'sha256='.hash_hmac('sha256', '{"otro":"cuerpo"}', self::SECRETO);

        $this->webhook($this->cambio([$this->boton("cancelar:{$this->cita->codigo}")]), $firmaDeOtro)->assertForbidden();

        $this->assertSame(EstadoCita::Pendiente, $this->citaActual()->estado);
    }

    public function test_en_produccion_sin_secreto_configurado_se_rechaza_todo(): void
    {
        config(['whatsapp.app_secret' => '']);
        $this->app['env'] = 'production';

        $this->postJson('/webhooks/whatsapp', $this->cambio())->assertForbidden();
    }

    public function test_fuera_de_produccion_sin_secreto_se_acepta_para_poder_probar_en_local(): void
    {
        config(['whatsapp.app_secret' => '']);

        $this->postJson('/webhooks/whatsapp', $this->cambio())->assertOk();
    }

    // --- Estados de los mensajes ----------------------------------------------------------

    public function test_actualiza_el_estado_de_entrega_y_lectura(): void
    {
        $this->webhook($this->cambio(estados: [['id' => 'wamid.ENVIADO', 'status' => 'delivered']]))->assertOk();
        $this->assertSame(EstadoMensaje::Entregado, $this->mensajeActual()->estado);

        $this->webhook($this->cambio(estados: [['id' => 'wamid.ENVIADO', 'status' => 'read']]))->assertOk();
        $this->assertSame(EstadoMensaje::Leido, $this->mensajeActual()->estado);
    }

    public function test_un_aviso_atrasado_no_hace_retroceder_el_estado(): void
    {
        $this->webhook($this->cambio(estados: [['id' => 'wamid.ENVIADO', 'status' => 'read']]));
        $this->webhook($this->cambio(estados: [['id' => 'wamid.ENVIADO', 'status' => 'sent']]));
        $this->webhook($this->cambio(estados: [['id' => 'wamid.ENVIADO', 'status' => 'delivered']]));

        $this->assertSame(EstadoMensaje::Leido, $this->mensajeActual()->estado);
    }

    public function test_registra_el_motivo_cuando_whatsapp_no_pudo_entregar(): void
    {
        $this->webhook($this->cambio(estados: [[
            'id' => 'wamid.ENVIADO',
            'status' => 'failed',
            'errors' => [['code' => 131026, 'title' => 'El mensaje no se pudo entregar']],
        ]]))->assertOk();

        $mensaje = $this->mensajeActual();
        $this->assertSame(EstadoMensaje::Fallido, $mensaje->estado);
        $this->assertSame('El mensaje no se pudo entregar', $mensaje->error);
    }

    public function test_un_fallo_tardio_no_borra_un_mensaje_ya_entregado(): void
    {
        $this->webhook($this->cambio(estados: [['id' => 'wamid.ENVIADO', 'status' => 'delivered']]));
        $this->webhook($this->cambio(estados: [['id' => 'wamid.ENVIADO', 'status' => 'failed']]));

        $this->assertSame(EstadoMensaje::Entregado, $this->mensajeActual()->estado);
    }

    public function test_ignora_estados_de_mensajes_que_no_conoce(): void
    {
        $this->webhook($this->cambio(estados: [['id' => 'wamid.DESCONOCIDO', 'status' => 'read']]))->assertOk();

        $this->assertSame(EstadoMensaje::Enviado, $this->mensajeActual()->estado);
    }

    // --- Confirmar y cancelar desde WhatsApp ---------------------------------------------------

    public function test_el_cliente_confirma_su_cita_con_el_boton(): void
    {
        $this->webhook($this->cambio([$this->boton("confirmar:{$this->cita->codigo}")]))->assertOk();

        $cita = $this->citaActual();
        $this->assertSame(EstadoCita::Confirmada, $cita->estado);
        $this->assertNotNull($cita->confirmada_en);

        $mensaje = $this->mensajeActual();
        $this->assertSame('confirmo', $mensaje->respuesta);
        $this->assertNotNull($mensaje->respondido_en);

        $this->assertCount(1, $this->whatsapp->textos);
        $this->assertSame('573001234567', $this->whatsapp->textos[0]['telefono']);
        $this->assertStringContainsString('quedó confirmada', $this->whatsapp->textos[0]['texto']);
        $this->assertStringContainsString('lunes 12 de octubre a las 10:00 a. m.', $this->whatsapp->textos[0]['texto']);
    }

    public function test_el_cliente_cancela_su_cita_y_el_cupo_queda_libre(): void
    {
        $this->webhook($this->cambio([$this->boton("cancelar:{$this->cita->codigo}")]))->assertOk();

        $cita = $this->citaActual();
        $this->assertSame(EstadoCita::Cancelada, $cita->estado);
        $this->assertSame('Cancelada por el cliente (WhatsApp)', $cita->motivo_cancelacion);
        $this->assertNotNull($cita->cancelada_en);
        $this->assertSame('cancelo', $this->mensajeActual()->respuesta);

        $this->assertStringContainsString('Cancelamos tu cita', $this->whatsapp->textos[0]['texto']);
        $this->assertStringContainsString("/b/{$this->barberia->slug}", $this->whatsapp->textos[0]['texto'], 'Incluye el enlace para volver a reservar.');

        // El horario vuelve a estar disponible para otra persona.
        $this->assertTrue(app(TenantContext::class)->ejecutarEn($this->barberia, fn () => app(Disponibilidad::class)
            ->huecos($this->barbero, Carbon::parse('2026-10-12'), 30, Carbon::parse('2026-10-12 00:00'))
            ->contains(fn ($h) => $h->format('H:i') === '10:00')));
    }

    public function test_tambien_entiende_el_formato_de_respuesta_interactiva(): void
    {
        $mensaje = ['from' => '573001234567', 'id' => 'wamid.IN1', 'type' => 'interactive', 'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => "confirmar:{$this->cita->codigo}", 'title' => 'Confirmar']]];

        $this->webhook($this->cambio([$mensaje]))->assertOk();

        $this->assertSame(EstadoCita::Confirmada, $this->citaActual()->estado);
    }

    public function test_si_meta_repite_el_aviso_no_se_responde_dos_veces(): void
    {
        $payload = $this->cambio([$this->boton("confirmar:{$this->cita->codigo}")]);

        $this->webhook($payload)->assertOk();
        $this->webhook($payload)->assertOk();

        $this->assertCount(1, $this->whatsapp->textos);
    }

    public function test_solo_el_dueno_del_celular_puede_responder_por_la_cita(): void
    {
        $this->webhook($this->cambio([$this->boton("cancelar:{$this->cita->codigo}", desde: '573109998877')]))->assertOk();

        $this->assertSame(EstadoCita::Pendiente, $this->citaActual()->estado, 'Otro número no puede cancelar la cita de alguien más.');
        $this->assertSame([], $this->whatsapp->textos);
    }

    public function test_ignora_codigos_inexistentes_y_formatos_raros(): void
    {
        foreach (['confirmar:zzzzzzzzzz', 'confirmar:', 'borrar:'.$this->cita->codigo, 'confirmar:'.$this->cita->codigo.'; DROP', 'hola'] as $payload) {
            $this->webhook($this->cambio([$this->boton($payload)]))->assertOk();
        }

        $this->assertSame(EstadoCita::Pendiente, $this->citaActual()->estado);
    }

    public function test_no_cambia_una_cita_que_ya_paso_o_ya_estaba_cancelada(): void
    {
        $this->citaActual()->update(['estado' => EstadoCita::Cancelada]);
        $this->webhook($this->cambio([$this->boton("confirmar:{$this->cita->codigo}")]))->assertOk();
        $this->assertSame(EstadoCita::Cancelada, $this->citaActual()->estado, 'Una cita cancelada no resucita con un botón viejo.');

        $this->citaActual()->update(['estado' => EstadoCita::Pendiente]);
        Carbon::setTestNow('2026-10-12 10:30:00');
        $this->webhook($this->cambio([$this->boton("cancelar:{$this->cita->codigo}")]))->assertOk();
        $this->assertSame(EstadoCita::Pendiente, $this->citaActual()->estado, 'Una cita que ya empezó no se modifica.');
    }

    public function test_los_mensajes_de_texto_libre_no_hacen_nada_por_ahora(): void
    {
        $texto = ['from' => '573001234567', 'id' => 'wamid.IN2', 'type' => 'text', 'text' => ['body' => 'cancelar mi cita']];

        $this->webhook($this->cambio([$texto]))->assertOk();

        $this->assertSame(EstadoCita::Pendiente, $this->citaActual()->estado);
        $this->assertSame([], $this->whatsapp->textos);
    }

    public function test_si_falla_la_respuesta_al_cliente_la_cita_igual_queda_actualizada(): void
    {
        $this->whatsapp->error = new WhatsAppError('Sin conexión', reintentable: true);

        $this->webhook($this->cambio([$this->boton("confirmar:{$this->cita->codigo}")]))->assertOk();

        $this->assertSame(EstadoCita::Confirmada, $this->citaActual()->estado);
    }

    public function test_la_respuesta_afecta_solo_a_la_cita_de_su_codigo_en_su_barberia(): void
    {
        $otra = Barberia::factory()->create();
        $ajena = Cita::factory()->create(['barberia_id' => $otra->id]);

        $this->webhook($this->cambio([$this->boton("confirmar:{$this->cita->codigo}")]))->assertOk();

        $this->assertSame(EstadoCita::Pendiente, Cita::withoutGlobalScopes()->find($ajena->id)->estado);
        $this->assertNull(app(TenantContext::class)->id(), 'El webhook no deja una barbería activa pegada.');
    }
}
