<?php

namespace App\Exceptions;

use DomainException;

/**
 * Una operación de caja no se puede hacer. El mensaje se muestra tal cual a la persona.
 */
class CobroNoPermitido extends DomainException
{
    public static function cajaCerrada(): self
    {
        return new self('La caja de ese día ya está cerrada. El dueño puede reabrirla para hacer cambios.');
    }

    public static function citaYaCobrada(): self
    {
        return new self('Esta cita ya fue cobrada.');
    }

    public static function citaNoCobrable(): self
    {
        return new self('Una cita cancelada o con inasistencia no se puede cobrar.');
    }

    public static function descuentoExcedeElTotal(): self
    {
        return new self('El descuento no puede ser mayor que el valor de los servicios.');
    }

    public static function sinItems(): self
    {
        return new self('Elige al menos un servicio para cobrar.');
    }

    public static function servicioNoOfrecido(): self
    {
        return new self('Ese barbero no realiza alguno de los servicios elegidos.');
    }

    public static function ventaYaAnulada(): self
    {
        return new self('Esta venta ya estaba anulada.');
    }

    public static function cierreYaExiste(): self
    {
        return new self('La caja de ese día ya fue cerrada.');
    }

    public static function fechaFutura(): self
    {
        return new self('No se puede cerrar la caja de un día que aún no ha pasado.');
    }
}
