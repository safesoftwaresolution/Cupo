<?php

namespace App\Http\Requests;

use App\Rules\CelularColombiano;
use Illuminate\Foundation\Http\FormRequest;

class ReservaPublicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Cualquier cliente puede reservar; la barbería sale del `slug` de la URL.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'servicios' => ['required', 'array', 'min:1', 'max:6'],
            'servicios.*' => ['integer'],
            'barbero_id' => ['nullable', 'integer'],
            'inicia_en' => ['required', 'date_format:Y-m-d H:i'],
            'nombre' => ['required', 'string', 'min:2', 'max:120'],
            'telefono' => ['required', 'string', new CelularColombiano],
            // Autorización de tratamiento de datos y de mensajes (Ley 1581 de 2012).
            'acepta' => ['accepted'],
            // Campo trampa: está oculto para personas; los bots lo llenan y se descartan.
            'sitio_web' => ['nullable', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'acepta.accepted' => 'Debes autorizar el tratamiento de tus datos para reservar.',
            'sitio_web.max' => 'No se pudo procesar la solicitud.',
        ];
    }
}
