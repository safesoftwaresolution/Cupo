<?php

namespace App\Enums;

enum EstadoMensaje: string
{
    case Pendiente = 'pendiente';
    case Enviado = 'enviado';
    case Entregado = 'entregado';
    case Leido = 'leido';
    case Fallido = 'fallido';
    /** No se envió porque ya no hacía falta (la cita se canceló, el cliente retiró su autorización…). */
    case Omitido = 'omitido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'En cola',
            self::Enviado => 'Enviado',
            self::Entregado => 'Entregado',
            self::Leido => 'Leído',
            self::Fallido => 'Falló',
            self::Omitido => 'Omitido',
        };
    }

    /**
     * Orden de avance de un mensaje. Meta puede avisar los estados fuera de orden,
     * y uno anterior nunca debe pisar a uno posterior.
     */
    public function rango(): int
    {
        return match ($this) {
            self::Pendiente => 0,
            self::Enviado => 1,
            self::Entregado => 2,
            self::Leido => 3,
            self::Fallido, self::Omitido => 0,
        };
    }
}
