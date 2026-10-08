<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCita;
use App\Enums\EstadoMensaje;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\MensajeWhatsapp;
use App\Support\TenantContext;
use App\Support\WhatsApp\MensajeRecordatorio;
use App\Support\WhatsApp\WhatsAppCliente;
use App\Support\WhatsApp\WhatsAppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de la WhatsApp Cloud API. Meta lo llama para avisar de dos cosas:
 *  - el estado de los mensajes enviados (enviado, entregado, leído, fallido);
 *  - las respuestas de los clientes, incluidos los botones Confirmar y Cancelar del recordatorio.
 *
 * Siempre responde 200 a lo que pasa la firma: si no, Meta reintenta durante días.
 */
class WhatsAppWebhookController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * Verificación inicial: Meta llama con un desafío al registrar el webhook.
     */
    public function verificar(Request $request): Response
    {
        $esperado = (string) config('whatsapp.verify_token');
        $recibido = (string) $request->query('hub_verify_token');

        abort_unless(
            $request->query('hub_mode') === 'subscribe' && $esperado !== '' && hash_equals($esperado, $recibido),
            403,
        );

        return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function recibir(Request $request, WhatsAppCliente $cliente): JsonResponse
    {
        $this->verificarFirma($request);

        foreach ((array) $request->input('entry', []) as $entrada) {
            foreach ((array) ($entrada['changes'] ?? []) as $cambio) {
                $valor = (array) ($cambio['value'] ?? []);

                foreach ((array) ($valor['statuses'] ?? []) as $estado) {
                    $this->actualizarEstado((array) $estado);
                }

                foreach ((array) ($valor['messages'] ?? []) as $mensaje) {
                    $this->procesarRespuesta((array) $mensaje, $cliente);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Comprueba que la petición viene de Meta: firma HMAC-SHA256 del cuerpo con el secreto de la app.
     * Sin secreto configurado solo se acepta fuera de producción, para poder probar en local.
     */
    private function verificarFirma(Request $request): void
    {
        $secreto = (string) config('whatsapp.app_secret');

        if ($secreto === '') {
            abort_if(app()->isProduction(), 403, 'Falta WHATSAPP_APP_SECRET.');

            return;
        }

        $esperada = 'sha256='.hash_hmac('sha256', $request->getContent(), $secreto);
        $recibida = $request->header('X-Hub-Signature-256');

        abort_unless(is_string($recibida) && hash_equals($esperada, $recibida), 403, 'Firma no válida.');
    }

    /**
     * @param  array<string, mixed>  $estado
     */
    private function actualizarEstado(array $estado): void
    {
        $idWhatsapp = $estado['id'] ?? null;

        if (! is_string($idWhatsapp)) {
            return;
        }

        $mensaje = MensajeWhatsapp::withoutGlobalScopes()->where('wa_message_id', $idWhatsapp)->first();

        if (! $mensaje) {
            return;
        }

        $this->tenant->ejecutarEn($mensaje->barberia_id, function () use ($mensaje, $estado) {
            match ($estado['status'] ?? null) {
                'sent' => $mensaje->avanzarA(EstadoMensaje::Enviado),
                'delivered' => $mensaje->avanzarA(EstadoMensaje::Entregado),
                'read' => $mensaje->avanzarA(EstadoMensaje::Leido),
                'failed' => $this->marcarFallido($mensaje, $estado),
                default => null,
            };
        });
    }

    /**
     * @param  array<string, mixed>  $estado
     */
    private function marcarFallido(MensajeWhatsapp $mensaje, array $estado): void
    {
        // Un "fallido" tardío no debe borrar un mensaje que ya se entregó.
        if ($mensaje->estado->rango() >= EstadoMensaje::Entregado->rango()) {
            return;
        }

        $detalle = $estado['errors'][0]['title'] ?? $estado['errors'][0]['message'] ?? 'WhatsApp no pudo entregar el mensaje.';

        $mensaje->update(['estado' => EstadoMensaje::Fallido, 'error' => (string) $detalle]);
    }

    /**
     * Solo se atienden los botones del recordatorio ("confirmar:CODIGO" y "cancelar:CODIGO").
     *
     * @param  array<string, mixed>  $mensaje
     */
    private function procesarRespuesta(array $mensaje, WhatsAppCliente $cliente): void
    {
        $contenido = $mensaje['button']['payload'] ?? $mensaje['interactive']['button_reply']['id'] ?? null;

        if (! is_string($contenido) || ! preg_match('/^(confirmar|cancelar):([a-z0-9]{6,12})$/', $contenido, $partes)) {
            return;
        }

        [, $accion, $codigo] = $partes;

        $barberiaId = Cita::withoutGlobalScopes()->where('codigo', $codigo)->value('barberia_id');

        if (! $barberiaId) {
            return;
        }

        $this->tenant->ejecutarEn($barberiaId, function () use ($accion, $codigo, $mensaje, $cliente) {
            $cita = Cita::query()->with(['cliente', 'barberia'])->where('codigo', $codigo)->first();

            // El código solo sirve si quien responde es el dueño del número de la cita.
            if (! $cita || $cita->cliente->telefono !== Cliente::normalizarTelefono((string) ($mensaje['from'] ?? ''))) {
                Log::warning('WhatsApp: respuesta que no corresponde a la cita', ['codigo' => $codigo]);

                return;
            }

            if (! in_array($cita->estado, [EstadoCita::Pendiente, EstadoCita::Confirmada], true) || $cita->inicia_en->isPast()) {
                return;
            }

            $respuesta = $accion === 'confirmar' ? $this->confirmar($cita) : $this->cancelar($cita);

            if ($respuesta === null) {
                return; // Sin cambios (ya estaba así): Meta puede repetir el aviso y no se responde dos veces.
            }

            MensajeWhatsapp::query()
                ->where('cita_id', $cita->id)
                ->where('tipo', MensajeWhatsapp::RECORDATORIO)
                ->update(['respuesta' => $accion === 'confirmar' ? 'confirmo' : 'cancelo', 'respondido_en' => now()]);

            try {
                $cliente->enviarTexto($cita->cliente->telefono, $respuesta);
            } catch (WhatsAppError $e) {
                Log::warning('WhatsApp: no se pudo enviar la respuesta al cliente', ['error' => $e->getMessage()]);
            }
        });
    }

    /**
     * @return string|null El texto con que se responde al cliente, o null si no hubo cambio.
     */
    private function confirmar(Cita $cita): ?string
    {
        if ($cita->estado === EstadoCita::Confirmada) {
            return null;
        }

        $cita->update(['estado' => EstadoCita::Confirmada, 'confirmada_en' => now()]);
        [$fecha, $hora] = MensajeRecordatorio::fechaYHora($cita);

        return "¡Gracias, {$this->nombre($cita)}! Tu cita del {$fecha} a las {$hora} quedó confirmada. Te esperamos en {$cita->barberia->nombre}.";
    }

    private function cancelar(Cita $cita): string
    {
        $cita->update([
            'estado' => EstadoCita::Cancelada,
            'cancelada_en' => now(),
            'motivo_cancelacion' => 'Cancelada por el cliente (WhatsApp)',
        ]);
        [$fecha, $hora] = MensajeRecordatorio::fechaYHora($cita);

        return "Listo, {$this->nombre($cita)}. Cancelamos tu cita del {$fecha} a las {$hora}. Cuando quieras volver a reservar: ".route('reserva.show', ['slug' => $cita->barberia->slug]);
    }

    private function nombre(Cita $cita): string
    {
        return strtok(trim($cita->cliente->nombre), ' ') ?: $cita->cliente->nombre;
    }
}
