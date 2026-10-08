<?php

namespace App\Support\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Cloud API de Meta (https://developers.facebook.com/docs/whatsapp/cloud-api).
 */
class CloudApiCliente implements WhatsAppCliente
{
    public function __construct(
        private readonly string $phoneNumberId,
        private readonly string $token,
        private readonly string $version,
    ) {}

    public function enviarPlantilla(string $telefono, string $plantilla, string $idioma, array $variables, array $botones = []): string
    {
        $componentes = [];

        if ($variables !== []) {
            $componentes[] = [
                'type' => 'body',
                'parameters' => array_map(fn (string $v) => ['type' => 'text', 'text' => $v], array_values($variables)),
            ];
        }

        foreach (array_values($botones) as $indice => $payload) {
            $componentes[] = [
                'type' => 'button',
                'sub_type' => 'quick_reply',
                'index' => (string) $indice,
                'parameters' => [['type' => 'payload', 'payload' => $payload]],
            ];
        }

        return $this->enviar([
            'messaging_product' => 'whatsapp',
            'to' => $telefono,
            'type' => 'template',
            'template' => [
                'name' => $plantilla,
                'language' => ['code' => $idioma],
                'components' => $componentes,
            ],
        ]);
    }

    public function enviarTexto(string $telefono, string $texto): string
    {
        return $this->enviar([
            'messaging_product' => 'whatsapp',
            'to' => $telefono,
            'type' => 'text',
            'text' => ['body' => $texto, 'preview_url' => false],
        ]);
    }

    /**
     * @param  array<string, mixed>  $cuerpo
     */
    private function enviar(array $cuerpo): string
    {
        try {
            $respuesta = Http::withToken($this->token)
                ->timeout(15)
                ->acceptJson()
                ->post("https://graph.facebook.com/{$this->version}/{$this->phoneNumberId}/messages", $cuerpo);
        } catch (ConnectionException $e) {
            throw new WhatsAppError('No se pudo conectar con WhatsApp: '.$e->getMessage(), reintentable: true);
        }

        if ($respuesta->failed()) {
            throw $this->errorDe($respuesta);
        }

        $id = $respuesta->json('messages.0.id');

        if (! is_string($id) || $id === '') {
            throw new WhatsAppError('WhatsApp respondió sin identificador de mensaje.');
        }

        return $id;
    }

    /**
     * Los errores 5xx y el límite de uso (429) se pueden reintentar; el resto (número inválido,
     * plantilla no aprobada, token vencido) no mejora por insistir.
     */
    private function errorDe(Response $respuesta): WhatsAppError
    {
        $detalle = $respuesta->json('error.message') ?? $respuesta->body();
        $codigo = $respuesta->json('error.code');

        return new WhatsAppError(
            "WhatsApp rechazó el mensaje ({$respuesta->status()}".($codigo ? ", código {$codigo}" : '')."): {$detalle}",
            reintentable: $respuesta->serverError() || $respuesta->status() === 429,
        );
    }
}
