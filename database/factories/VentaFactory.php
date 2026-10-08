<?php

namespace Database\Factories;

use App\Enums\MetodoPago;
use App\Models\Barberia;
use App\Models\Barbero;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venta>
 */
class VentaFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = 25000;
        $porcentaje = 50;

        return [
            'barberia_id' => Barberia::factory(),
            'barbero_id' => fn (array $attrs) => Barbero::factory()->create(['barberia_id' => $attrs['barberia_id']])->id,
            'subtotal' => $subtotal,
            'descuento' => 0,
            'propina' => 0,
            'total' => $subtotal,
            'metodo_pago' => MetodoPago::Efectivo,
            'comision_porcentaje' => $porcentaje,
            'comision_monto' => Venta::calcularComision($subtotal, 0, $porcentaje),
            'pagada_en' => now(),
        ];
    }
}
