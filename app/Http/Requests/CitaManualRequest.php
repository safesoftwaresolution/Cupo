<?php

namespace App\Http\Requests;

use App\Rules\CelularColombiano;
use Illuminate\Foundation\Http\FormRequest;

class CitaManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo controla el middleware `rol:dueno,recepcion` de la ruta.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'barbero_id' => ['required', 'integer'],
            'servicios' => ['required', 'array', 'min:1', 'max:6'],
            'servicios.*' => ['integer'],
            'inicia_en' => ['required', 'date_format:Y-m-d H:i'],
            'nombre' => ['required', 'string', 'min:2', 'max:120'],
            'telefono' => ['required', 'string', new CelularColombiano],
            'notas' => ['nullable', 'string', 'max:500'],
        ];
    }
}
