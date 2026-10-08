<?php

/*
 * WhatsApp Business Platform (Cloud API de Meta).
 *
 * `log`: modo de pruebas. No sale ningún mensaje real; solo se registra en storage/logs.
 * `cloud`: envía de verdad. Requiere las credenciales de abajo (ver docs/whatsapp.md).
 */
return [
    'driver' => env('WHATSAPP_DRIVER', 'log'),

    'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),

    // Número desde el que se envía (ID del número en Meta, no el teléfono).
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    // Token permanente de un usuario del sistema de Meta.
    'token' => env('WHATSAPP_TOKEN'),

    // Secreto de la app de Meta: con él se comprueba que el webhook realmente viene de Meta.
    'app_secret' => env('WHATSAPP_APP_SECRET'),

    // Palabra que se escribe al registrar el webhook en Meta.
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

    // Plantilla aprobada por Meta para el recordatorio (texto sugerido en docs/whatsapp.md).
    'plantilla_recordatorio' => env('WHATSAPP_TEMPLATE_RECORDATORIO', 'recordatorio_cita'),

    'idioma' => env('WHATSAPP_LANGUAGE', 'es'),
];
