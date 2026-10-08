<?php

namespace App\Actions;

use App\Enums\EstadoCita;
use App\Enums\OrigenCita;
use App\Exceptions\CupoNoDisponible;
use App\Models\Barbero;
use App\Models\Cita;
use App\Models\Cliente;
use App\Support\Disponibilidad;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reserva un cupo. Es el único punto por el que se crean citas.
 *
 * Evita la doble reserva así: dentro de una transacción bloquea la fila del
 * barbero (`FOR UPDATE` en PostgreSQL), con lo que dos reservas simultáneas del
 * mismo barbero se atienden una tras otra, y recién ahí se revisa que el
 * horario siga libre con las mismas reglas que usa la pantalla de reservas.
 */
class ReservarCita
{
    public function __construct(private Disponibilidad $disponibilidad) {}

    /**
     * @param  list<int>  $servicioIds
     * @param  array{nombre: string, telefono: string, acepta_whatsapp?: bool}  $cliente
     * @param  CarbonInterface|null  $desde  Primer instante reservable; por defecto se aplica la anticipación mínima de la barbería.
     *
     * @throws CupoNoDisponible
     */
    public function __invoke(
        Barbero $barbero,
        array $servicioIds,
        CarbonInterface $inicio,
        array $cliente,
        OrigenCita $origen,
        ?CarbonInterface $desde = null,
        ?string $notas = null,
    ): Cita {
        return DB::transaction(function () use ($barbero, $servicioIds, $inicio, $cliente, $origen, $desde, $notas) {
            $barbero = Barbero::query()->whereKey($barbero->id)->lockForUpdate()->firstOrFail();

            $servicios = $barbero->servicios()
                ->where('servicios.activo', true)
                ->whereIn('servicios.id', $servicioIds)
                ->get();

            if ($servicios->count() !== count(array_unique($servicioIds))) {
                throw CupoNoDisponible::servicioNoOfrecido();
            }

            $duracion = (int) $servicios->sum('duracion_minutos');
            $libre = $this->disponibilidad->huecos($barbero, $inicio, $duracion, $desde)
                ->contains(fn ($hueco) => $hueco->equalTo($inicio));

            if (! $libre) {
                throw CupoNoDisponible::ocupado();
            }

            $cita = Cita::create([
                'codigo' => $this->codigoUnico(),
                'barbero_id' => $barbero->id,
                'cliente_id' => $this->resolverCliente($cliente)->id,
                'inicia_en' => $inicio,
                'termina_en' => $inicio->copy()->addMinutes($duracion),
                'estado' => EstadoCita::Pendiente,
                'origen' => $origen,
                'precio_total' => (int) $servicios->sum(fn ($s) => $s->pivot->precio_especial ?? $s->precio),
                'duracion_total_minutos' => $duracion,
                'notas' => $notas,
            ]);

            // Se guarda el nombre, la duración y el precio de ese momento: si luego cambian, la cita no.
            $cita->servicios()->attach($servicios->mapWithKeys(fn ($s) => [
                $s->id => [
                    'nombre' => $s->nombre,
                    'duracion_minutos' => $s->duracion_minutos,
                    'precio' => $s->pivot->precio_especial ?? $s->precio,
                ],
            ])->all());

            return $cita;
        });
    }

    /**
     * Un mismo celular es un mismo cliente dentro de la barbería.
     *
     * @param  array{nombre: string, telefono: string, acepta_whatsapp?: bool}  $datos
     */
    private function resolverCliente(array $datos): Cliente
    {
        $cliente = Cliente::firstOrNew(['telefono' => Cliente::normalizarTelefono($datos['telefono'])]);

        if (! $cliente->exists || blank($cliente->nombre)) {
            $cliente->nombre = $datos['nombre'];
        }

        // El consentimiento solo se otorga, nunca se retira por una reserva.
        if (! empty($datos['acepta_whatsapp']) && ! $cliente->acepta_whatsapp) {
            $cliente->acepta_whatsapp = true;
            $cliente->acepto_en = now();
        }

        $cliente->save();

        return $cliente;
    }

    private function codigoUnico(): string
    {
        do {
            $codigo = Str::lower(Str::random(10));
        } while (Cita::query()->withoutGlobalScopes()->where('codigo', $codigo)->exists());

        return $codigo;
    }
}
