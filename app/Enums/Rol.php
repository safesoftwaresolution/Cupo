<?php

namespace App\Enums;

enum Rol: string
{
    case Dueno = 'dueno';
    case Recepcion = 'recepcion';
    case Barbero = 'barbero';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Dueno => 'Dueño',
            self::Recepcion => 'Recepción',
            self::Barbero => 'Barbero',
        };
    }
}
