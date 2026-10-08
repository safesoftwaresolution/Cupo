<?php

namespace App\Actions;

use App\Enums\EstadoCita;
use App\Exceptions\CobroNoPermitido;
use App\Models\CierreCaja;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

/**
 * Anula una venta (nunca se borra). Deja de contar en la caja y en las comisiones.
 * Si venía de una cita, la cita vuelve a "Confirmada" para poder cobrarse de nuevo.
 */
class AnularVenta
{
    /**
     * @throws CobroNoPermitido
     */
    public function __invoke(Venta $venta, string $motivo, User $usuario): Venta
    {
        return DB::transaction(function () use ($venta, $motivo, $usuario) {
            $venta = Venta::query()->whereKey($venta->id)->lockForUpdate()->firstOrFail();

            if ($venta->estaAnulada()) {
                throw CobroNoPermitido::ventaYaAnulada();
            }

            // Una caja cerrada es un hecho contable: para tocarla primero hay que reabrirla.
            if (CierreCaja::query()->whereDate('fecha', $venta->pagada_en->toDateString())->exists()) {
                throw CobroNoPermitido::cajaCerrada();
            }

            $venta->update([
                'anulada_en' => now(),
                'motivo_anulacion' => $motivo,
                'anulada_por' => $usuario->id,
            ]);

            if ($venta->cita && $venta->cita->estado === EstadoCita::Completada) {
                $venta->cita->update(['estado' => EstadoCita::Confirmada]);
            }

            return $venta;
        });
    }
}
