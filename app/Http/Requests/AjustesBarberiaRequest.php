<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AjustesBarberiaRequest extends FormRequest
{
    /** Valores que se ofrecen en pantalla; el servidor no acepta otros. */
    public const INTERVALOS = [10, 15, 20, 30, 60];

    public const ANTICIPACIONES = [0, 30, 60, 120, 240, 1440];

    public const HORAS_RECORDATORIO = [2, 3, 6, 12, 24, 48];

    public function authorize(): bool
    {
        return true; // El acceso lo controla el middleware `rol:dueno` de la ruta.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+()\-\s]*$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'intervalo_agenda' => ['required', 'integer', Rule::in(self::INTERVALOS)],
            'anticipacion_minima' => ['required', 'integer', Rule::in(self::ANTICIPACIONES)],
            'dias_reserva' => ['required', 'integer', 'between:1,90'],
            'maneja_comisiones' => ['required', 'boolean'],
            'recordatorios_activos' => ['required', 'boolean'],
            'recordatorio_horas' => ['required', 'integer', Rule::in(self::HORAS_RECORDATORIO)],
        ];
    }

    public function attributes(): array
    {
        return [
            'intervalo_agenda' => 'intervalo de la agenda',
            'anticipacion_minima' => 'anticipación mínima',
            'dias_reserva' => 'días de reserva',
            'recordatorio_horas' => 'horas del recordatorio',
        ];
    }

    public function messages(): array
    {
        return [
            'telefono.regex' => 'El teléfono solo puede tener números, espacios y los signos + ( ) -.',
        ];
    }
}
