<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCita;
use App\Models\Barbero;
use App\Models\Cita;
use App\Models\Horario;
use App\Models\Servicio;
use App\Models\Venta;
use App\Support\Estadisticas;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** Periodos que el dueño puede elegir para las estadísticas. */
    private const RANGOS = ['7', '30', '90', 'mes'];

    public function __invoke(Request $request, Estadisticas $estadisticas): Response
    {
        $rango = in_array($request->query('rango'), self::RANGOS, true) ? $request->query('rango') : '30';
        $esDueno = $request->user()->esDueno();
        $barberia = $request->user()->barberia;

        $pasos = [
            ['clave' => 'servicios', 'titulo' => 'Crea tus servicios y precios', 'hecho' => Servicio::query()->exists(), 'href' => route('servicios.index')],
            ['clave' => 'barberos', 'titulo' => 'Agrega a tus barberos', 'hecho' => Barbero::query()->exists(), 'href' => route('barberos.index')],
            ['clave' => 'horarios', 'titulo' => 'Define el horario de cada barbero', 'hecho' => Horario::query()->exists(), 'href' => route('barberos.index')],
        ];

        $hoy = Cita::query()
            ->whereBetween('inicia_en', [now()->startOfDay(), now()->endOfDay()])
            ->whereIn('estado', EstadoCita::queOcupanCupo())
            ->with(['cliente:id,nombre', 'barbero:id,nombre', 'servicios'])
            ->orderBy('inicia_en')
            ->get();

        return Inertia::render('Dashboard', [
            'rango' => $rango,
            // Las estadísticas son solo del dueño. Es una función para que al cambiar de periodo
            // el navegador pueda pedir únicamente esto, sin recalcular el resto del inicio.
            'analitica' => $esDueno ? fn () => $this->analitica($estadisticas, $rango, $barberia->maneja_comisiones) : null,
            'pasos' => $pasos,
            'urlReserva' => route('reserva.show', ['slug' => $request->user()->barberia->slug]),
            'hoy' => [
                'total' => $hoy->count(),
                'ingresos' => (int) $hoy->sum('precio_total'),
                'cobrado' => (int) Venta::query()->vigentes()->delDia(now())->sum('total'),
                'proximas' => $hoy
                    ->filter(fn (Cita $c) => $c->termina_en->isFuture())
                    ->take(5)
                    ->map(fn (Cita $c) => [
                        'id' => $c->id,
                        'hora' => $c->inicia_en->format('H:i'),
                        'cliente' => $c->cliente->nombre,
                        'barbero' => $c->barbero->nombre,
                        'servicios' => $c->servicios->pluck('pivot.nombre')->join(', '),
                        'estado' => $c->estado->value,
                        'estado_etiqueta' => $c->estado->etiqueta(),
                    ])
                    ->values(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function analitica(Estadisticas $estadisticas, string $rango, bool $manejaComisiones): array
    {
        $hoy = now();

        [$desde, $hasta] = match ($rango) {
            '7' => [$hoy->copy()->subDays(6), $hoy],
            '90' => [$hoy->copy()->subDays(89), $hoy],
            'mes' => [$hoy->copy()->startOfMonth(), $hoy],
            default => [$hoy->copy()->subDays(29), $hoy],
        };

        return $estadisticas->resumen($desde, $hasta, $manejaComisiones);
    }
}
