<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class HorarioRequest extends FormRequest
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
            'franjas' => ['present', 'array', 'max:56'],
            'franjas.*.dia_semana' => ['required', 'integer', 'between:1,7'],
            'franjas.*.hora_inicio' => ['required', 'date_format:H:i'],
            'franjas.*.hora_fin' => ['required', 'date_format:H:i', 'after:franjas.*.hora_inicio'],
        ];
    }

    public function messages(): array
    {
        return [
            'franjas.*.hora_fin.after' => 'La hora de fin debe ser posterior a la de inicio.',
            'franjas.*.hora_inicio.date_format' => 'La hora de inicio no es válida.',
            'franjas.*.hora_fin.date_format' => 'La hora de fin no es válida.',
        ];
    }

    /**
     * Dos franjas del mismo día no pueden cruzarse.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $porDia = collect($this->input('franjas', []))->groupBy('dia_semana');

                foreach ($porDia as $franjas) {
                    $ordenadas = $franjas->sortBy('hora_inicio')->values();

                    for ($i = 1; $i < $ordenadas->count(); $i++) {
                        if ($ordenadas[$i]['hora_inicio'] < $ordenadas[$i - 1]['hora_fin']) {
                            $validator->errors()->add('franjas', 'Hay franjas que se cruzan en el mismo día.');

                            return;
                        }
                    }
                }
            },
        ];
    }
}
