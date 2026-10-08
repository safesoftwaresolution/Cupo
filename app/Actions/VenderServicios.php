<?php

namespace App\Actions;

use App\Enums\MetodoPago;
use App\Exceptions\CobroNoPermitido;
use App\Models\Barbero;
use App\Models\User;
use App\Models\Venta;

/**
 * Venta de mostrador: para quien llega sin cita. Usa el precio vigente de cada servicio
 * para ese barbero (su precio especial, si lo tiene).
 */
class VenderServicios
{
    public function __construct(private RegistrarVenta $registrarVenta) {}

    /**
     * @param  list<int>  $servicioIds
     *
     * @throws CobroNoPermitido
     */
    public function __invoke(Barbero $barbero, array $servicioIds, int $descuento, int $propina, MetodoPago $metodo, User $usuario): Venta
    {
        $ids = array_values(array_unique($servicioIds));

        $servicios = $barbero->servicios()
            ->where('servicios.activo', true)
            ->whereIn('servicios.id', $ids)
            ->get();

        if ($servicios->count() !== count($ids)) {
            throw CobroNoPermitido::servicioNoOfrecido();
        }

        $items = $servicios->map(fn ($s) => [
            'servicio_id' => $s->id,
            'descripcion' => $s->nombre,
            'cantidad' => 1,
            'precio_unitario' => (int) ($s->pivot->precio_especial ?? $s->precio),
        ])->all();

        return ($this->registrarVenta)($barbero, $items, $descuento, $propina, $metodo, $usuario);
    }
}
