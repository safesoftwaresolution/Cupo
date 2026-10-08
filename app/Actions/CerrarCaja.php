<?php

namespace App\Actions;

use App\Enums\MetodoPago;
use App\Exceptions\CobroNoPermitido;
use App\Models\CierreCaja;
use App\Models\User;
use App\Models\Venta;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cierre de caja del día: compara el efectivo que debería haber (ventas vigentes en efectivo,
 * propinas incluidas) con el que se contó. Las ventas del día quedan ligadas al cierre.
 */
class CerrarCaja
{
    /**
     * @throws CobroNoPermitido
     */
    public function __invoke(CarbonInterface $fecha, int $efectivoContado, ?string $notas, User $usuario): CierreCaja
    {
        if ($fecha->copy()->startOfDay()->isFuture()) {
            throw CobroNoPermitido::fechaFutura();
        }

        return DB::transaction(function () use ($fecha, $efectivoContado, $notas, $usuario) {
            if (CierreCaja::query()->whereDate('fecha', $fecha->toDateString())->exists()) {
                throw CobroNoPermitido::cierreYaExiste();
            }

            $ventas = Venta::query()->vigentes()->delDia($fecha);
            $esperado = (int) (clone $ventas)->where('metodo_pago', MetodoPago::Efectivo)->sum('total');

            $cierre = CierreCaja::create([
                'user_id' => $usuario->id,
                'fecha' => $fecha->toDateString(),
                'efectivo_esperado' => $esperado,
                'efectivo_contado' => $efectivoContado,
                'diferencia' => $efectivoContado - $esperado,
                'notas' => $notas,
            ]);

            $ventas->update(['cierre_caja_id' => $cierre->id]);

            return $cierre;
        });
    }
}
