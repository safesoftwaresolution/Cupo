<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DisponibilidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Público: solo consulta horarios libres.
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
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }
}
