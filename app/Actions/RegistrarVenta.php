<?php

namespace App\Actions;

use App\Enums\MetodoPago;
use App\Exceptions\CobroNoPermitido;
use App\Models\Barbero;
use App\Models\Cita;
use App\Models\CierreCaja;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

/**
 * Registra un cobro. Es el único punto por el que nace una venta.
 *
 * Reglas de dinero (todo en pesos enteros):
 *   total      = subtotal - descuento + propina      (lo que paga el cliente)
 *   comisión   = porcentaje del barbero × (subtotal - descuento)
 *   la propina va completa para el barbero y no genera comisión.
 * El porcentaje se guarda en la venta: cambiarlo después no altera ventas pasadas.
 */
class RegistrarVenta
{
    /**
     * @param  list<array{servicio_id: int|null, descripcion: string, cantidad: int, precio_unitario: int}>  $items
     *
     * @throws CobroNoPermitido
     */
    public function __invoke(
        Barbero $barbero,
        array $items,
        int $descuento,
        int $propina,
        MetodoPago $metodo,
        User $usuario,
        ?Cita $cita = null,
        ?Cliente $cliente = null,
    ): Venta {
        if ($items === []) {
            throw CobroNoPermitido::sinItems();
        }

        $subtotal = (int) collect($items)->sum(fn (array $i) => $i['cantidad'] * $i['precio_unitario']);

        if ($descuento > $subtotal) {
            throw CobroNoPermitido::descuentoExcedeElTotal();
        }

        return DB::transaction(function () use ($barbero, $items, $subtotal, $descuento, $propina, $metodo, $usuario, $cita, $cliente) {
            $ahora = now();

            if (CierreCaja::query()->whereDate('fecha', $ahora->toDateString())->exists()) {
                throw CobroNoPermitido::cajaCerrada();
            }

            $venta = Venta::create([
                'cita_id' => $cita?->id,
                'barbero_id' => $barbero->id,
                'cliente_id' => $cliente?->id ?? $cita?->cliente_id,
                'user_id' => $usuario->id,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'propina' => $propina,
                'total' => $subtotal - $descuento + $propina,
                'metodo_pago' => $metodo,
                'comision_porcentaje' => $barbero->comision_porcentaje,
                'comision_monto' => Venta::calcularComision($subtotal, $descuento, $barbero->comision_porcentaje),
                'pagada_en' => $ahora,
            ]);

            $venta->items()->createMany($items);

            return $venta;
        });
    }
}
