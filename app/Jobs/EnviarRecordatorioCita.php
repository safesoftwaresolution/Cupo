<?php

namespace App\Jobs;

use App\Enums\EstadoCita;
use App\Enums\EstadoMensaje;
use App\Models\Cita;
use App\Models\MensajeWhatsapp;
use App\Support\TenantContext;
use App\Support\WhatsApp\MensajeRecordatorio;
use App\Support\WhatsApp\WhatsAppCliente;
use App\Support\WhatsApp\WhatsAppError;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Envía el recordatorio de una cita. El mensaje ya existe en estado "pendiente" (lo crea el comando
 * que programa los envíos), y eso es lo que evita enviar dos veces.
 */
class EnviarRecordatorioCita implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $mensajeId, public int $barberiaId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(WhatsAppCliente $cliente, TenantContext $tenant): void
    {
        $tenant->ejecutarEn($this->barberiaId, function () use ($cliente) {
            $mensaje = MensajeWhatsapp::query()->with(['cita.cliente', 'cita.barberia', 'cita.servicios'])->find($this->mensajeId);

            // Si ya se procesó (por un reintento o una ejecución doble) no se vuelve a enviar.
            if (! $mensaje || $mensaje->estado !== EstadoMensaje::Pendiente) {
                return;
            }

            $cita = $mensaje->cita;

            if ($motivo = $this->motivoParaOmitir($cita)) {
                $mensaje->update(['estado' => EstadoMensaje::Omitido, 'error' => $motivo]);

                return;
            }

            $plantilla = (string) config('whatsapp.plantilla_recordatorio');

            try {
                $idWhatsapp = $cliente->enviarPlantilla(
                    $mensaje->telefono,
                    $plantilla,
                    (string) config('whatsapp.idioma'),
                    MensajeRecordatorio::variables($cita),
                    MensajeRecordatorio::botones($cita),
                );
            } catch (WhatsAppError $e) {
                // Un fallo pasajero (red, límite de uso) se reintenta; uno definitivo (número inválido) se registra.
                if ($e->reintentable && $this->attempts() < $this->tries) {
                    throw $e;
                }

                $mensaje->update(['estado' => EstadoMensaje::Fallido, 'error' => Str::limit($e->getMessage(), 500)]);

                return;
            }

            $mensaje->update([
                'estado' => EstadoMensaje::Enviado,
                'plantilla' => $plantilla,
                'wa_message_id' => $idWhatsapp,
                'enviado_en' => now(),
                'error' => null,
            ]);
            $cita->update(['recordatorio_enviado_en' => now()]);
        });
    }

    /**
     * Cuando se agotan los intentos por un error inesperado, el mensaje no queda "en cola" para siempre.
     */
    public function failed(Throwable $e): void
    {
        MensajeWhatsapp::withoutGlobalScopes()
            ->whereKey($this->mensajeId)
            ->where('estado', EstadoMensaje::Pendiente)
            ->update(['estado' => EstadoMensaje::Fallido, 'error' => Str::limit($e->getMessage(), 500)]);
    }

    /**
     * Entre que se programó y se envía pueden cambiar las cosas: se vuelve a comprobar.
     */
    private function motivoParaOmitir(?Cita $cita): ?string
    {
        return match (true) {
            $cita === null, ! in_array($cita->estado, [EstadoCita::Pendiente, EstadoCita::Confirmada], true) => 'La cita ya no está activa.',
            $cita->inicia_en->isPast() => 'La cita ya pasó.',
            ! $cita->barberia->activa, ! $cita->barberia->recordatorios_activos => 'Los recordatorios están desactivados.',
            ! $cita->cliente->acepta_whatsapp => 'El cliente no autorizó recibir mensajes.',
            default => null,
        };
    }
}
