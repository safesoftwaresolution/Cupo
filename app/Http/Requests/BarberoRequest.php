<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BarberoRequest extends FormRequest
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
            'telefono' => ['nullable', 'string', 'max:20'],
            // Solo se pide si la barbería maneja comisiones; si no, el campo ni se muestra.
            'comision_porcentaje' => [Rule::requiredIf(fn () => $this->user()->barberia->maneja_comisiones), 'nullable', 'numeric', 'between:0,100'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'activo' => ['required', 'boolean'],
            'servicios' => ['present', 'array'],
            // Solo servicios vivos de esta misma barbería.
            'servicios.*' => [
                'integer',
                Rule::exists('servicios', 'id')
                    ->where('barberia_id', app(TenantContext::class)->id())
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'telefono' => 'teléfono',
            'comision_porcentaje' => 'comisión',
            'servicios.*' => 'servicio',
        ];
    }
}
