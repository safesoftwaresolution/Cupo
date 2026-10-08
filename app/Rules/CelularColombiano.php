<?php

namespace App\Rules;

use App\Models\Cliente;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Celular colombiano: 10 dígitos que empiezan por 3, con o sin indicativo (+57).
 * Es el número al que se enviarán los recordatorios por WhatsApp.
 */
class CelularColombiano implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalizado = Cliente::normalizarTelefono((string) $value);

        if (! preg_match('/^573\d{9}$/', $normalizado)) {
            $fail('Escribe un celular colombiano válido: 10 dígitos que empiecen por 3.');
        }
    }
}
