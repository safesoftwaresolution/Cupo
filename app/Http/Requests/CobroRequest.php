<?php

namespace App\Http\Requests;

use App\Enums\MetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos de un cobro: usado al cobrar una cita y en la venta de mostrador.
 */
class CobroRequest extends FormRequest
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
            'metodo_pago' => ['required', Rule::enum(MetodoPago::class)],
            'descuento' => ['nullable', 'integer', 'between:0,10000000'],
            'propina' => ['nullable', 'integer', 'between:0,10000000'],
            // Solo en la venta de mostrador.
            'barbero_id' => [Rule::requiredIf($this->routeIs('caja.ventas.store')), 'integer'],
            'servicios' => [Rule::requiredIf($this->routeIs('caja.ventas.store')), 'array', 'min:1', 'max:10'],
            'servicios.*' => ['integer'],
        ];
    }

    public function descuento(): int
    {
        return (int) $this->input('descuento', 0);
    }

    public function propina(): int
    {
        return (int) $this->input('propina', 0);
    }

    public function metodo(): MetodoPago
    {
        return MetodoPago::from($this->input('metodo_pago'));
    }
}
