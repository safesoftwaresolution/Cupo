<?php

namespace App\Http\Controllers;

use App\Actions\ReservarCita;
use App\Enums\EstadoCita;
use App\Enums\OrigenCita;
use App\Exceptions\CupoNoDisponible;
use App\Http\Requests\CitaManualRequest;
use App\Http\Requests\DisponibilidadRequest;
use App\Models\Barbero;
use App\Models\Cita;
use App\Models\Horario;
use App\Models\Servicio;
use App\Support\Disponibilidad;
use App\Support\Fechas;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Agenda interna del día para dueño y recepción.
 */
class AgendaController extends Controller
{
    public function index(Request $request): Response
    {
        $fecha = Fechas::dia($request->query('fecha'));

        $barberos = Barbero::query()
            ->activos()
            ->with(['servicios:id', 'horarios' => fn ($q) => $q->where('dia_semana', $fecha->dayOfWeekIso)->orderBy('hora_inicio')])
            ->orderBy('nombre')
            ->get()
            ->map(fn (Barbero $b) => [
                'id' => $b->id,
                'nombre' => $b->nombre,
                'color' => $b->color,
                'servicios' => $b->servicios->pluck('id')->all(),
                'franjas' => $b->horarios->map(fn (Horario $h) => [
                    'inicio' => substr($h->hora_inicio, 0, 5),
                    'fin' => substr($h->hora_fin, 0, 5),
                ])->all(),
            ]);

        $citas = Cita::query()
            ->whereBetween('inicia_en', [$fecha->copy()->startOfDay(), $fecha->copy()->endOfDay()])
            ->with(['cliente:id,nombre,telefono', 'servicios'])
            ->orderBy('inicia_en')
            ->get()
            ->map(fn (Cita $c) => [
                'id' => $c->id,
                'barbero_id' => $c->barbero_id,
                'inicia' => $c->inicia_en->format('H:i'),
                'termina' => $c->termina_en->format('H:i'),
                'estado' => $c->estado->value,
                'estado_etiqueta' => $c->estado->etiqueta(),
                'cliente' => ['nombre' => $c->cliente->nombre, 'telefono' => $c->cliente->telefono],
                'servicios' => $c->servicios->pluck('pivot.nombre')->join(', '),
                'precio_total' => $c->precio_total,
                'origen' => $c->origen->value,
                'notas' => $c->notas,
                'transiciones' => collect($c->estado->transicionesPermitidas())
                    ->map(fn (EstadoCita $e) => ['valor' => $e->value, 'etiqueta' => $e->etiqueta()])
                    ->all(),
            ]);

        return Inertia::render('agenda/Index', [
            'fecha' => $fecha->toDateString(),
            'hoy' => now()->toDateString(),
            'barberos' => $barberos,
            'citas' => $citas,
            'servicios' => Servicio::query()->activos()->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre', 'duracion_minutos', 'precio']),
        ]);
    }

    /**
     * Horas libres para el formulario de nueva cita. A diferencia de la reserva pública,
     * no exige anticipación: el personal puede registrar a quien llega sin cita.
     */
    public function disponibilidad(DisponibilidadRequest $request, Disponibilidad $disponibilidad): JsonResponse
    {
        $resultado = $disponibilidad->paraServicios(
            $request->input('servicios'),
            $request->integer('barbero_id') ?: null,
            Carbon::createFromFormat('!Y-m-d', $request->input('fecha'))->startOfDay(),
            now()->startOfDay(),
        );

        return response()->json([
            'duracion' => $resultado['duracion'],
            'barberos' => $resultado['barberos']->map(fn (array $x) => [
                'id' => $x['barbero']->id,
                'horas' => $x['horas']->map(fn (Carbon $h) => $h->format('H:i'))->all(),
            ])->values(),
        ]);
    }

    public function store(CitaManualRequest $request, ReservarCita $reservar): RedirectResponse
    {
        $barbero = Barbero::query()->activos()->findOrFail($request->integer('barbero_id'));
        $inicio = Carbon::createFromFormat('!Y-m-d H:i', $request->input('inicia_en'));

        try {
            $reservar(
                $barbero,
                array_map('intval', $request->input('servicios')),
                $inicio,
                ['nombre' => $request->input('nombre'), 'telefono' => $request->input('telefono')],
                OrigenCita::Manual,
                now()->startOfDay(),
                $request->input('notas'),
            );
        } catch (CupoNoDisponible $e) {
            return back()->withErrors(['inicia_en' => $e->getMessage()]);
        }

        return back()->with('success', 'Cita agendada.');
    }

    public function estado(Request $request, Cita $cita): RedirectResponse
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::enum(EstadoCita::class)],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $nuevo = EstadoCita::from($datos['estado']);

        if (! in_array($nuevo, $cita->estado->transicionesPermitidas(), true)) {
            return back()->withErrors(['estado' => "Una cita {$cita->estado->etiqueta()} no puede pasar a {$nuevo->etiqueta()}."]);
        }

        $cambios = ['estado' => $nuevo];

        if ($nuevo === EstadoCita::Confirmada) {
            $cambios['confirmada_en'] = now();
        }

        if ($nuevo === EstadoCita::Cancelada) {
            $cambios['cancelada_en'] = now();
            $cambios['motivo_cancelacion'] = $datos['motivo'] ?? 'Cancelada por la barbería';
        }

        $cita->update($cambios);

        return back()->with('success', "Cita marcada como {$nuevo->etiqueta()}.");
    }

}
