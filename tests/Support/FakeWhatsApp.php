<?php

namespace Tests\Support;

use App\Support\WhatsApp\WhatsAppCliente;
use App\Support\WhatsApp\WhatsAppError;

/**
 * Cliente de WhatsApp para pruebas: anota lo que se enviaría y puede simular fallos.
 */
class FakeWhatsApp implements WhatsAppCliente
{
    /** @var list<array{telefono: string, plantilla: string, idioma: string, variables: list<string>, botones: list<string>}> */
    public array $plantillas = [];

    /** @var list<array{telefono: string, texto: string}> */
    public array $textos = [];

    public ?WhatsAppError $error = null;

    public function enviarPlantilla(string $telefono, string $plantilla, string $idioma, array $variables, array $botones = []): string
    {
        if ($this->error) {
            throw $this->error;
        }

        $this->plantillas[] = compact('telefono', 'plantilla', 'idioma', 'variables', 'botones');

        return 'wamid.PLANTILLA'.count($this->plantillas);
    }

    public function enviarTexto(string $telefono, string $texto): string
    {
        if ($this->error) {
            throw $this->error;
        }

        $this->textos[] = compact('telefono', 'texto');

        return 'wamid.TEXTO'.count($this->textos);
    }
}
