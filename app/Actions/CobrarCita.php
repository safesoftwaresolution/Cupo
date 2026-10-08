<?php

namespace App\Actions;

use App\Enums\EstadoCita;
use App\Enums\MetodoPago;
use App\Exceptions\CobroNoPermitido;
use App\Models\Cita;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

/**
 * Cobra una cita: crea la venta con lo que se acordó al reservar y la marca como completada.
 */
class CobrarCita
{
    public function __construct(private RegistrarVenta $registrarVenta) {}

    /**
     * @throws CobroNoPermitido
     */
    public function __invoke(Cita $cita, int $descuento, int $propina, MetodoPago $metodo, User $usuario): Venta
    {
        return DB::transaction(function () use ($cita, $descuento, $propina, $metodo, $usuario) {
            // Se bloquea la cita para que dos cobros simultáneos no generen dos ventas.
            $cita = Cita::query()->whereKey($cita->id)->lockForUpdate()->firstOrFail();

            if (! in_array($cita->estado, [EstadoCita::Pendiente, EstadoCita::Confirmada, EstadoCita::Completada], true)) {
                throw CobroNoPermitido::citaNoCobrable();
            }

            if ($cita->venta()->exists()) {
                throw CobroNoPermitido::citaYaCobrada();
            }

            // Se lee la copia guardada al reservar, directo de la tabla: sigue válida aunque el servicio ya no exista.
            $items = DB::table('cita_servicio')
                ->where('cita_id', $cita->id)
                ->get()
                ->map(fn ($s) => [
                    'servicio_id' => $s->servicio_id,
                    'descripcion' => $s->nombre,
                    'cantidad' => 1,
                    'precio_unitario' => (int) $s->precio,
                ])
                ->all();

            $venta = ($this->registrarVenta)($cita->barbero, $items, $descuento, $propina, $metodo, $usuario, $cita);

            if ($cita->estado !== EstadoCita::Completada) {
                $cita->update(['estado' => EstadoCita::Completada]);
            }

            return $venta;
        });
    }
}
