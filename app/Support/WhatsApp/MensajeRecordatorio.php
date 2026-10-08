<?php

namespace App\Support\WhatsApp;

use App\Models\Cita;

/**
 * Cómo se arma el recordatorio. Las variables siguen el orden de la plantilla que se registra en Meta
 * (texto sugerido en docs/whatsapp.md):
 *
 *   Hola {{1}}, te recordamos tu cita en {{2}}: {{3}} el {{4}} a las {{5}}. ¿Nos confirmas?
 *   [Confirmar] [Cancelar]
 */
class MensajeRecordatorio
{
    /**
     * @return list<string>
     */
    public static function variables(Cita $cita): array
    {
        [$fecha, $hora] = self::fechaYHora($cita);

        return [
            // Solo el primer nombre: más cercano y evita nombres muy largos.
            strtok(trim($cita->cliente->nombre), ' ') ?: $cita->cliente->nombre,
            $cita->barberia->nombre,
            $cita->servicios->pluck('pivot.nombre')->join(' + '),
            $fecha,
            $hora,
        ];
    }

    /**
     * "viernes 9 de octubre" y "9:30 a. m.", como se leen en una conversación.
     *
     * @return array{0: string, 1: string}
     */
    public static function fechaYHora(Cita $cita): array
    {
        $inicio = $cita->inicia_en->copy()->locale('es');

        return [
            $inicio->translatedFormat('l j \d\e F'),
            $inicio->format('g:i').($inicio->hour < 12 ? ' a. m.' : ' p. m.'),
        ];
    }

    /**
     * Contenido de los botones de respuesta rápida. Llevan el código de la cita para saber
     * a cuál se refiere la respuesta.
     *
     * @return list<string>
     */
    public static function botones(Cita $cita): array
    {
        return ["confirmar:{$cita->codigo}", "cancelar:{$cita->codigo}"];
    }
}
