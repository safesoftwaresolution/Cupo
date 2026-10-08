<?php

namespace App\Support;

use Carbon\Carbon;

class Fechas
{
    /**
     * Interpreta un texto `AAAA-MM-DD` como el inicio de ese día. Si no es válido, devuelve hoy.
     */
    public static function dia(?string $valor): Carbon
    {
        try {
            return Carbon::createFromFormat('!Y-m-d', (string) $valor)->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }
}
