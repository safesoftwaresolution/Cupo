<?php

namespace App\Enums;

enum EstadoCita: string
{
    case Pendiente = 'pendiente';
    case Confirmada = 'confirmada';
    case Completada = 'completada';
    case Cancelada = 'cancelada';
    case NoAsistio = 'no_asistio';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Confirmada => 'Confirmada',
            self::Completada => 'Completada',
            self::Cancelada => 'Cancelada',
            self::NoAsistio => 'No asistió',
        };
    }

    /**
     * A qué estados puede pasar una cita desde este. Los estados finales no cambian:
     * si hubo un error, se crea una cita nueva.
     *
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            // El orden es el de los botones en la agenda: la acción destructiva va al final.
            self::Pendiente => [self::Confirmada, self::Completada, self::NoAsistio, self::Cancelada],
            self::Confirmada => [self::Completada, self::NoAsistio, self::Cancelada],
            self::Completada, self::Cancelada, self::NoAsistio => [],
        };
    }

    /**
     * Estados que ocupan el cupo del barbero en la agenda.
     *
     * @return list<self>
     */
    public static function queOcupanCupo(): array
    {
        return [self::Pendiente, self::Confirmada, self::Completada];
    }
}
