<?php

namespace App\Support\WhatsApp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Modo de pruebas: no sale ningún mensaje, solo queda en el registro.
 */
class LogCliente implements WhatsAppCliente
{
    public function enviarPlantilla(string $telefono, string $plantilla, string $idioma, array $variables, array $botones = []): string
    {
        $id = 'log.'.Str::uuid();

        Log::info('[WhatsApp de prueba] plantilla', compact('id', 'telefono', 'plantilla', 'idioma', 'variables', 'botones'));

        return $id;
    }

    public function enviarTexto(string $telefono, string $texto): string
    {
        $id = 'log.'.Str::uuid();

        Log::info('[WhatsApp de prueba] texto', compact('id', 'telefono', 'texto'));

        return $id;
    }
}
