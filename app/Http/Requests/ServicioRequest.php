<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServicioRequest extends FormRequest
{
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
            'descripcion' => ['nullable', 'string', 'max:500'],
            'duracion_minutos' => ['required', 'integer', 'between:5,480'],
            'precio' => ['required', 'integer', 'between:0,10000000'],
            'activo' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'descripcion' => 'descripción',
            'duracion_minutos' => 'duración',
        ];
    }
}
