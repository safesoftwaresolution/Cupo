<?php

namespace Tests\Feature;

use App\Actions\ReservarCita;
use App\Enums\EstadoCita;
use App\Enums\EstadoMensaje;
use App\Enums\OrigenCita;
use App\Jobs\EnviarRecordatorioCita;
use App\Models\Barberia;
use App\Models\Barbero;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\MensajeWhatsapp;
use App\Models\Servicio;
use App\Support\TenantContext;
use App\Support\WhatsApp\WhatsAppCliente;
use App\Support\WhatsApp\WhatsAppError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\ArmaBarberia;
use Tests\Support\FakeWhatsApp;
use Tests\TestCase;

class RecordatoriosTest extends TestCase
{
    use ArmaBarberia, RefreshDatabase;

    private FakeWhatsApp $whatsapp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->armarBarberia();
        app(TenantContext::class)->olvidar();

        $this->whatsapp = new FakeWhatsApp;
        $this->app->instance(WhatsAppCliente::class, $this->whatsapp);

        // Domingo 12:00: la cita del lunes a las 10:00 queda a 22 horas.
        Carbon::setTestNow('2026-10-11 12:00:00');
    }

    private function programar(): void
    {
        $this->artisan('recordatorios:enviar')->assertSuccessful();
    }

    private function mensaje(): MensajeWhatsapp
    {
        return MensajeWhatsapp::withoutGlobalScopes()->firstOrFail();
    }

    /**
     * El cliente retira su autorización (fuera del contexto de la barbería, por eso se salta el filtro).
     */
    private function quitarAutorizacion(Cita $cita): void
    {
        Cliente::withoutGlobalScopes()->whereKey($cita->cliente_id)->update(['acepta_whatsapp' => false]);
    }

    /**
     * Procesa el envío como lo haría el worker de colas.
     */
    private function enviar(MensajeWhatsapp $mensaje, int $intento = 1): void
    {
        $job = new EnviarRecordatorioCita($mensaje->id, $mensaje->barberia_id);
        $job->setJob(new class($intento) extends FakeJob
        {
            public function __construct(private int $intento) {}

            public function attempts(): int
            {
                return $this->intento;
            }
        });

        $job->handle($this->whatsapp, app(TenantContext::class));
    }

    // --- Qué citas reciben recordatorio ---------------------------------------------------

    public function test_programa_el_recordatorio_de_una_cita_dentro_de_la_ventana(): void
    {
        Queue::fake();
        $cita = $this->reservar('10:00');

        $this->programar();

        Queue::assertPushed(EnviarRecordatorioCita::class, 1);
        $mensaje = $this->mensaje();
        $this->assertSame($cita->id, $mensaje->cita_id);
        $this->assertSame(EstadoMensaje::Pendiente, $mensaje->estado);
        $this->assertSame('573001234567', $mensaje->telefono);
        $this->assertSame($this->barberia->id, $mensaje->barberia_id);
    }

    public function test_tambien_avisa_a_las_citas_ya_confirmadas(): void
    {
        Queue::fake();
        $this->reservar('10:00')->update(['estado' => EstadoCita::Confirmada]);

        $this->programar();

        Queue::assertPushed(EnviarRecordatorioCita::class, 1);
    }

    public function test_no_avisa_si_la_cita_esta_muy_lejos(): void
    {
        Queue::fake();
        $this->reservar('10:00');
        Carbon::setTestNow('2026-10-10 09:00:00'); // faltan más de 24 horas

        $this->programar();

        Queue::assertNothingPushed();
    }

    public function test_no_avisa_si_ya_casi_empieza(): void
    {
        Queue::fake();
        $this->reservar('10:00');
        Carbon::setTestNow('2026-10-12 09:45:00'); // faltan 15 minutos

        $this->programar();

        Queue::assertNothingPushed();
    }

    public function test_no_avisa_citas_canceladas_completadas_o_con_inasistencia(): void
    {
        Queue::fake();
        foreach ([EstadoCita::Cancelada, EstadoCita::Completada, EstadoCita::NoAsistio] as $i => $estado) {
            $this->reservar(['10:00', '11:00', '15:00'][$i], telefono: '30011122'.(10 + $i))->update(['estado' => $estado]);
        }

        $this->programar();

        Queue::assertNothingPushed();
    }

    public function test_no_avisa_a_quien_no_autorizo_mensajes(): void
    {
        Queue::fake();
        $this->quitarAutorizacion($this->reservar('10:00'));

        $this->programar();

        Queue::assertNothingPushed();
        $this->assertSame(0, MensajeWhatsapp::withoutGlobalScopes()->count());
    }

    public function test_respeta_las_horas_de_anticipacion_de_la_barberia(): void
    {
        Queue::fake();
        $this->reservar('10:00'); // a 22 horas

        $this->barberia->update(['recordatorio_horas' => 12]);
        $this->programar();
        Queue::assertNothingPushed();

        $this->barberia->update(['recordatorio_horas' => 24]);
        $this->programar();
        Queue::assertPushed(EnviarRecordatorioCita::class, 1);
    }

    public function test_no_avisa_si_la_barberia_desactivo_los_recordatorios_o_esta_inactiva(): void
    {
        Queue::fake();
        $this->reservar('10:00');

        $this->barberia->update(['recordatorios_activos' => false]);
        $this->programar();

        $this->barberia->update(['recordatorios_activos' => true, 'activa' => false]);
        $this->programar();

        Queue::assertNothingPushed();
    }

    public function test_correrlo_varias_veces_no_duplica_el_recordatorio(): void
    {
        Queue::fake();
        $this->reservar('10:00');

        $this->programar();
        $this->programar();
        $this->programar();

        Queue::assertPushed(EnviarRecordatorioCita::class, 1);
        $this->assertSame(1, MensajeWhatsapp::withoutGlobalScopes()->count());
    }

    public function test_atiende_a_varias_barberias_sin_mezclarlas(): void
    {
        Queue::fake();
        $this->reservar('10:00');

        $otra = Barberia::factory()->create(['recordatorio_horas' => 24]);
        $barbero = Barbero::factory()->create(['barberia_id' => $otra->id]);
        $servicio = Servicio::factory()->create(['barberia_id' => $otra->id, 'duracion_minutos' => 30]);
        $barbero->servicios()->attach($servicio->id);
        $this->horario($barbero, 1, '09:00', '12:00');
        $citaAjena = app(TenantContext::class)->ejecutarEn($otra, fn () => app(ReservarCita::class)(
            $barbero, [$servicio->id], Carbon::parse('2026-10-12 11:00'),
            ['nombre' => 'Ana', 'telefono' => '3115556677', 'acepta_whatsapp' => true],
            OrigenCita::Manual, Carbon::parse('2026-10-12 00:00'),
        ));

        $this->programar();

        Queue::assertPushed(EnviarRecordatorioCita::class, 2);
        $this->assertSame($otra->id, MensajeWhatsapp::withoutGlobalScopes()->where('cita_id', $citaAjena->id)->value('barberia_id'));
        $this->assertSame($this->barberia->id, MensajeWhatsapp::withoutGlobalScopes()->where('cita_id', '!=', $citaAjena->id)->value('barberia_id'));
    }

    // --- El envío -------------------------------------------------------------------------------

    public function test_envia_la_plantilla_con_los_datos_y_los_botones_de_la_cita(): void
    {
        Queue::fake();
        $cita = $this->reservar('10:00');
        $this->programar();

        $this->enviar($this->mensaje());

        $this->assertCount(1, $this->whatsapp->plantillas);
        $enviado = $this->whatsapp->plantillas[0];
        $this->assertSame('573001234567', $enviado['telefono']);
        $this->assertSame('recordatorio_cita', $enviado['plantilla']);
        $this->assertSame('es', $enviado['idioma']);
        $this->assertSame(['Cliente', $this->barberia->nombre, 'Corte', 'lunes 12 de octubre', '10:00 a. m.'], $enviado['variables']);
        $this->assertSame(["confirmar:{$cita->codigo}", "cancelar:{$cita->codigo}"], $enviado['botones']);

        $mensaje = $this->mensaje();
        $this->assertSame(EstadoMensaje::Enviado, $mensaje->estado);
        $this->assertSame('wamid.PLANTILLA1', $mensaje->wa_message_id);
        $this->assertNotNull($mensaje->enviado_en);
        $this->assertNotNull($cita->fresh()->recordatorio_enviado_en);
    }

    public function test_la_hora_de_la_tarde_se_escribe_como_p_m(): void
    {
        Queue::fake();
        $this->reservar('15:30');
        Carbon::setTestNow('2026-10-12 09:00:00'); // la cita queda a unas 6 horas
        $this->programar();

        $this->enviar($this->mensaje());

        $this->assertSame('3:30 p. m.', $this->whatsapp->plantillas[0]['variables'][4]);
    }

    public function test_no_envia_dos_veces_si_el_trabajo_se_ejecuta_de_nuevo(): void
    {
        Queue::fake();
        $this->reservar('10:00');
        $this->programar();

        $this->enviar($this->mensaje());
        $this->enviar($this->mensaje());

        $this->assertCount(1, $this->whatsapp->plantillas);
    }

    public function test_si_la_cita_se_cancelo_antes_del_envio_no_se_manda_nada(): void
    {
        Queue::fake();
        $cita = $this->reservar('10:00');
        $this->programar();
        $cita->update(['estado' => EstadoCita::Cancelada]);

        $this->enviar($this->mensaje());

        $this->assertSame([], $this->whatsapp->plantillas);
        $this->assertSame(EstadoMensaje::Omitido, $this->mensaje()->estado);
    }

    public function test_si_el_cliente_retiro_su_autorizacion_antes_del_envio_no_se_manda_nada(): void
    {
        Queue::fake();
        $cita = $this->reservar('10:00');
        $this->programar();
        $this->quitarAutorizacion($cita);

        $this->enviar($this->mensaje());

        $this->assertSame([], $this->whatsapp->plantillas);
        $this->assertSame(EstadoMensaje::Omitido, $this->mensaje()->estado);
    }

    public function test_si_la_cita_ya_paso_no_se_manda_nada(): void
    {
        Queue::fake();
        $this->reservar('10:00');
        $this->programar();
        Carbon::setTestNow('2026-10-12 10:30:00');

        $this->enviar($this->mensaje());

        $this->assertSame([], $this->whatsapp->plantillas);
    }

    public function test_un_rechazo_definitivo_queda_registrado_sin_reintentar(): void
    {
        Queue::fake();
        $this->reservar('10:00');
        $this->programar();
        $this->whatsapp->error = new WhatsAppError('Número de WhatsApp no válido.');

        $this->enviar($this->mensaje()); // no lanza excepción: no hay nada que reintentar

        $mensaje = $this->mensaje();
        $this->assertSame(EstadoMensaje::Fallido, $mensaje->estado);
        $this->assertStringContainsString('no válido', $mensaje->error);
    }

    public function test_un_fallo_pasajero_se_reintenta_y_el_mensaje_sigue_en_cola(): void
    {
        Queue::fake();
        $this->reservar('10:00');
        $this->programar();
        $this->whatsapp->error = new WhatsAppError('Servicio no disponible.', reintentable: true);

        try {
            $this->enviar($this->mensaje(), intento: 1);
            $this->fail('Debió lanzar la excepción para que la cola reintente.');
        } catch (WhatsAppError) {
            // esperado
        }

        $this->assertSame(EstadoMensaje::Pendiente, $this->mensaje()->estado);
    }

    public function test_en_el_ultimo_intento_un_fallo_pasajero_se_registra_como_fallido(): void
    {
        Queue::fake();
        $this->reservar('10:00');
        $this->programar();
        $this->whatsapp->error = new WhatsAppError('Servicio no disponible.', reintentable: true);

        $this->enviar($this->mensaje(), intento: 3);

        $this->assertSame(EstadoMensaje::Fallido, $this->mensaje()->estado);
    }

    public function test_si_se_agotan_los_intentos_por_un_error_inesperado_no_queda_en_cola_para_siempre(): void
    {
        Queue::fake();
        $this->reservar('10:00');
        $this->programar();
        $mensaje = $this->mensaje();

        (new EnviarRecordatorioCita($mensaje->id, $mensaje->barberia_id))->failed(new \RuntimeException('Algo se rompió'));

        $this->assertSame(EstadoMensaje::Fallido, $this->mensaje()->estado);
        $this->assertSame('Algo se rompió', $this->mensaje()->error);
    }

    public function test_el_trabajo_programado_corre_con_el_comando_y_la_cola_de_verdad(): void
    {
        // Sin Queue::fake: la cola de pruebas es síncrona, así que el comando programa Y envía.
        $this->reservar('10:00');

        $this->programar();

        $this->assertCount(1, $this->whatsapp->plantillas);
        $this->assertSame(EstadoMensaje::Enviado, $this->mensaje()->estado);
    }
}
