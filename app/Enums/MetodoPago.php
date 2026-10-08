<?php

namespace App\Enums;

enum MetodoPago: string
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';
    case Nequi = 'nequi';
    case Daviplata = 'daviplata';
    case Tarjeta = 'tarjeta';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Transferencia => 'Transferencia',
            self::Nequi => 'Nequi',
            self::Daviplata => 'Daviplata',
            self::Tarjeta => 'Tarjeta',
        };
    }
}
