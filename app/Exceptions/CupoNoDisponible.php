<?php

namespace App\Exceptions;

use DomainException;

/**
 * El horario pedido no se puede reservar (ocupado, fuera de horario o ya pasó).
 * El mensaje está pensado para mostrarse tal cual a la persona.
 */
class CupoNoDisponible extends DomainException
{
    public static function ocupado(): self
    {
        return new self('Ese horario ya no está disponible. Elige otro.');
    }

    public static function servicioNoOfrecido(): self
    {
        return new self('Ese barbero no realiza alguno de los servicios elegidos.');
    }
}
