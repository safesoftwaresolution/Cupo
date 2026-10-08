<?php

namespace App\Support;

use App\Enums\EstadoCita;
use App\Models\Barbero;
use App\Models\Bloqueo;
use App\Models\Cita;
use App\Models\Horario;
use App\Models\Servicio;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Calcula los cupos libres de un barbero. Es la única fuente de verdad:
 * la pantalla de reservas y la validación al guardar usan estas mismas reglas.
 */
class Disponibilidad
{
    /**
     * Horas de inicio posibles para una cita de `$duracionMinutos` en el día `$fecha`.
     *
     * Respeta el horario semanal, los bloqueos, las citas que ocupan cupo, el
     * intervalo de la agenda y los límites de anticipación de la barbería.
     * `$desde` reemplaza la anticipación mínima (el personal puede agendar "para ya").
     *
     * @return Collection<int, Carbon>
     */
    public function huecos(Barbero $barbero, CarbonInterface $fecha, int $duracionMinutos, ?CarbonInterface $desde = null): Collection
    {
        $barberia = $barbero->barberia;
        $dia = Carbon::instance($fecha)->startOfDay();
        $finDelDia = $dia->copy()->endOfDay();

        $desde ??= now()->addMinutes($barberia->anticipacion_minima);
        $limite = now()->addDays($barberia->dias_reserva)->endOfDay();

        $franjas = Horario::query()
            ->where('barbero_id', $barbero->id)
            ->where('dia_semana', $dia->dayOfWeekIso)
            ->orderBy('hora_inicio')
            ->get();

        if ($franjas->isEmpty() || $duracionMinutos <= 0) {
            return collect();
        }

        $bloqueos = Bloqueo::query()
            ->where('barbero_id', $barbero->id)
            ->where('inicia_en', '<', $finDelDia)
            ->where('termina_en', '>', $dia)
            ->get(['inicia_en', 'termina_en']);

        $citas = Cita::query()
            ->where('barbero_id', $barbero->id)
            ->whereIn('estado', EstadoCita::queOcupanCupo())
            ->where('inicia_en', '<', $finDelDia)
            ->where('termina_en', '>', $dia)
            ->get(['inicia_en', 'termina_en']);

        $ocupados = $bloqueos->concat($citas);
        $paso = max(5, $barberia->intervalo_agenda);
        $huecos = collect();

        foreach ($franjas as $franja) {
            $finFranja = $dia->copy()->setTimeFromTimeString($franja->hora_fin);

            for ($inicio = $dia->copy()->setTimeFromTimeString($franja->hora_inicio); ; $inicio->addMinutes($paso)) {
                $termina = $inicio->copy()->addMinutes($duracionMinutos);

                if ($termina->gt($finFranja)) {
                    break;
                }

                if ($inicio->lt($desde) || $inicio->gt($limite)) {
                    continue;
                }

                if ($ocupados->contains(fn ($o) => $o->inicia_en->lt($termina) && $o->termina_en->gt($inicio))) {
                    continue;
                }

                $huecos->push($inicio->copy());
            }
        }

        return $huecos->unique(fn (Carbon $h) => $h->timestamp)->values();
    }

    /**
     * Barberos activos que realizan TODOS los servicios pedidos, con sus horas libres ese día.
     *
     * @param  list<int>  $servicioIds
     * @return array{duracion: int, barberos: Collection<int, array{barbero: Barbero, horas: Collection<int, Carbon>}>}
     */
    public function paraServicios(array $servicioIds, ?int $barberoId, CarbonInterface $fecha, ?CarbonInterface $desde = null): array
    {
        $servicios = Servicio::query()->activos()->whereIn('id', $servicioIds)->get();
        $ids = $servicios->pluck('id')->all();

        // Si algún servicio no existe o está oculto, no hay nada que ofrecer.
        if ($servicios->count() !== count(array_unique($servicioIds))) {
            return ['duracion' => 0, 'barberos' => collect()];
        }

        $duracion = (int) $servicios->sum('duracion_minutos');

        $barberos = Barbero::query()
            ->activos()
            ->when($barberoId, fn ($q) => $q->whereKey($barberoId))
            ->whereHas('servicios', fn ($q) => $q->whereIn('servicios.id', $ids)->where('servicios.activo', true), '>=', count($ids))
            ->orderBy('nombre')
            ->get();

        $conHoras = $barberos->map(fn (Barbero $b) => [
            'barbero' => $b,
            'horas' => $this->huecos($b, $fecha, $duracion, $desde),
        ]);

        return [
            'duracion' => $duracion,
            'barberos' => $conHoras,
        ];
    }
}
