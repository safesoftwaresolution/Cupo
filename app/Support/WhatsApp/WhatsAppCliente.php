<?php

namespace App\Support\WhatsApp;

/**
 * Lo único que Cupo necesita de WhatsApp: enviar una plantilla aprobada o un texto libre.
 * Tener una interfaz permite probar todo sin salir a Internet y cambiar de proveedor sin tocar el resto.
 */
interface WhatsAppCliente
{
    /**
     * Envía una plantilla aprobada por Meta. Las plantillas son lo único que se puede enviar
     * cuando el cliente no ha escrito en las últimas 24 horas.
     *
     * @param  string  $telefono  Solo dígitos con indicativo, ej. 573001234567.
     * @param  list<string>  $variables  Valores de {{1}}, {{2}}… en el orden de la plantilla.
     * @param  list<string>  $botones  Contenido (payload) de cada botón de respuesta rápida, en orden.
     * @return string El identificador del mensaje en WhatsApp.
     *
     * @throws WhatsAppError
     */
    public function enviarPlantilla(string $telefono, string $plantilla, string $idioma, array $variables, array $botones = []): string;

    /**
     * Envía un texto libre. Solo es válido dentro de las 24 horas siguientes a que el cliente escribió
     * (por ejemplo, al responder a un botón), y esas respuestas no tienen costo.
     *
     * @throws WhatsAppError
     */
    public function enviarTexto(string $telefono, string $texto): string;
}
