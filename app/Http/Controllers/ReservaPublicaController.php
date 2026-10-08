<?php

namespace App\Http\Controllers;

use App\Actions\ReservarCita;
use App\Enums\EstadoCita;
use App\Enums\OrigenCita;
use App\Exceptions\CupoNoDisponible;
use App\Http\Requests\DisponibilidadRequest;
use App\Http\Requests\ReservaPublicaRequest;
use App\Models\Barberia;
use App\Models\Barbero;
use App\Models\Cita;
use App\Models\Servicio;
use App\Support\Disponibilidad;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reserva pública de una barbería (`/b/{slug}`). La barbería activa la fija
 * el middleware `reserva`; todas las consultas ya salen filtradas por ella.
 */
class ReservaPublicaController extends Controller
{
    public function show(Request $request): Response
    {
        $barberia = $this->barberia($request);

        $barberos = Barbero::query()
            ->activos()
            ->has('horarios')
            ->with('servicios:id')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Barbero $b) => [
                'id' => $b->id,
                'nombre' => $b->nombre,
                'color' => $b->color,
                'servicios' => $b->servicios->pluck('id')->all(),
            ]);

        $servicios = Servicio::query()
            ->activos()
            ->whereIn('id', $barberos->flatMap(fn ($b) => $b['servicios'])->unique()->all())
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'descripcion', 'duracion_minutos', 'precio']);

        return Inertia::render('reserva/Show', [
            'barberia' => $this->datosBarberia($barberia),
            'servicios' => $servicios,
            'barberos' => $barberos,
            'hoy' => now()->toDateString(),
            'diasReserva' => $barberia->dias_reserva,
        ]);
    }

    public function disponibilidad(DisponibilidadRequest $request, Disponibilidad $disponibilidad): JsonResponse
    {
        $resultado = $disponibilidad->paraServicios(
            $request->input('servicios'),
            $request->integer('barbero_id') ?: null,
            Carbon::createFromFormat('!Y-m-d', $request->input('fecha'))->startOfDay(),
        );

        $barberos = $resultado['barberos']->map(fn (array $x) => [
            'id' => $x['barbero']->id,
            'nombre' => $x['barbero']->nombre,
            'horas' => $x['horas']->map(fn (Carbon $h) => $h->format('H:i'))->all(),
        ]);

        return response()->json([
            'duracion' => $resultado['duracion'],
            'horas' => $barberos->flatMap(fn ($b) => $b['horas'])->unique()->sort()->values(),
            'barberos' => $barberos->values(),
        ]);
    }

    public function store(ReservaPublicaRequest $request, ReservarCita $reservar, Disponibilidad $disponibilidad, string $slug): RedirectResponse
    {
        $inicio = Carbon::createFromFormat('!Y-m-d H:i', $request->input('inicia_en'));
        $servicios = array_map('intval', $request->input('servicios'));

        try {
            $barbero = $request->integer('barbero_id')
                ? Barbero::query()->activos()->findOrFail($request->integer('barbero_id'))
                : $this->barberoLibre($disponibilidad, $servicios, $inicio);

            $cita = $reservar(
                $barbero,
                $servicios,
                $inicio,
                [
                    'nombre' => $request->input('nombre'),
                    'telefono' => $request->input('telefono'),
                    'acepta_whatsapp' => true,
                ],
                OrigenCita::Web,
            );
        } catch (CupoNoDisponible $e) {
            return back()->withErrors(['inicia_en' => $e->getMessage()]);
        }

        return to_route('reserva.cita', ['slug' => $slug, 'codigo' => $cita->codigo]);
    }

    public function cita(Request $request, string $slug, string $codigo): Response
    {
        $cita = $this->buscarCita($codigo);

        return Inertia::render('reserva/Cita', [
            'barberia' => $this->datosBarberia($this->barberia($request)),
            'cita' => [
                'codigo' => $cita->codigo,
                'estado' => $cita->estado->value,
                'estado_etiqueta' => $cita->estado->etiqueta(),
                'inicia_en' => $cita->inicia_en->toIso8601String(),
                'termina_en' => $cita->termina_en->toIso8601String(),
                'barbero' => $cita->barbero->nombre,
                'cliente' => $cita->cliente->nombre,
                'servicios' => $cita->servicios->pluck('pivot.nombre')->all(),
                'precio_total' => $cita->precio_total,
                'cancelable' => $cita->cancelablePorCliente(),
            ],
        ]);
    }

    public function cancelar(string $slug, string $codigo): RedirectResponse
    {
        $cita = $this->buscarCita($codigo);

        if (! $cita->cancelablePorCliente()) {
            return back()->withErrors(['cita' => 'Esta cita ya no se puede cancelar desde aquí. Comunícate con la barbería.']);
        }

        $cita->update([
            'estado' => EstadoCita::Cancelada,
            'cancelada_en' => now(),
            'motivo_cancelacion' => 'Cancelada por el cliente',
        ]);

        return back()->with('success', 'Tu cita fue cancelada. El cupo quedó libre para otra persona.');
    }

    private function barberia(Request $request): Barberia
    {
        return $request->attributes->get('barberia');
    }

    /**
     * @return array{nombre: string, slug: string, ciudad: string|null, direccion: string|null, telefono: string|null}
     */
    private function datosBarberia(Barberia $barberia): array
    {
        return $barberia->only(['nombre', 'slug', 'ciudad', 'direccion', 'telefono']);
    }

    private function buscarCita(string $codigo): Cita
    {
        return Cita::query()
            ->where('codigo', $codigo)
            ->with(['barbero', 'cliente', 'servicios'])
            ->firstOrFail();
    }

    /**
     * Para "cualquier barbero": el que tenga el horario libre y el día más despejado.
     *
     * @param  list<int>  $servicios
     */
    private function barberoLibre(Disponibilidad $disponibilidad, array $servicios, Carbon $inicio): Barbero
    {
        $candidato = $disponibilidad->paraServicios($servicios, null, $inicio->copy()->startOfDay())['barberos']
            ->filter(fn (array $x) => $x['horas']->contains(fn ($h) => $h->equalTo($inicio)))
            ->sortByDesc(fn (array $x) => $x['horas']->count())
            ->first();

        return $candidato['barbero'] ?? throw CupoNoDisponible::ocupado();
    }
}
