<?php

namespace App\Support;

use App\Enums\EstadoCita;
use App\Enums\MetodoPago;
use App\Enums\OrigenCita;
use App\Models\Barbero;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Horario;
use App\Models\Venta;
use App\Models\VentaItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Estadísticas del negocio para el panel del dueño.
 *
 * Todo se calcula en PHP sobre las filas del periodo (una barbería mueve unos cientos de citas al mes),
 * así no depende de funciones de fecha propias de cada base de datos. Siempre dentro de la barbería activa.
 *
 * Cada indicador se compara con el periodo inmediatamente anterior de la misma duración.
 */
class Estadisticas
{
    /** Días sin venir a partir de los cuales un cliente habitual se considera "en riesgo". */
    public const DIAS_EN_RIESGO = 45;

    private const DIAS_SEMANA = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

    /**
     * @return array<string, mixed>
     */
    public function resumen(Carbon $desde, Carbon $hasta, bool $manejaComisiones): array
    {
        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->endOfDay();
        $dias = (int) $desde->diffInDays($hasta->copy()->startOfDay()) + 1;

        $antesHasta = $desde->copy()->subSecond();
        $antesDesde = $desde->copy()->subDays($dias);

        $ventas = $this->ventas($antesDesde, $hasta);
        $actuales = $ventas->filter(fn (Venta $v) => $v->pagada_en->betweenIncluded($desde, $hasta));
        $anteriores = $ventas->filter(fn (Venta $v) => $v->pagada_en->betweenIncluded($antesDesde, $antesHasta));

        $citas = $this->citas($antesDesde, $hasta);
        $citasActuales = $citas->filter(fn (Cita $c) => $c->inicia_en->betweenIncluded($desde, $hasta));
        $citasAnteriores = $citas->filter(fn (Cita $c) => $c->inicia_en->betweenIncluded($antesDesde, $antesHasta));

        $porDia = $this->ventasPorDia($actuales, $desde, $dias);
        $porDiaAnterior = $this->ventasPorDia($anteriores, $antesDesde, $dias);

        return [
            'periodo' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'dias' => $dias,
            ],
            'kpis' => $this->kpis($actuales, $anteriores, $citasActuales, $citasAnteriores, $desde, $hasta, $antesDesde, $antesHasta, $manejaComisiones, $porDia),
            'ventas_por_dia' => collect($porDia)->map(fn (array $d, int $i) => [
                'fecha' => $d['fecha'],
                'valor' => $d['valor'],
                'anterior' => $porDiaAnterior[$i]['valor'] ?? 0,
            ])->values()->all(),
            'demanda' => $this->demanda($citasActuales),
            'servicios' => $this->topServicios($actuales),
            'barberos' => $this->porBarbero($actuales, $manejaComisiones),
            'metodos' => $this->porMetodo($actuales),
            'origen' => $this->porOrigen($citasActuales),
            'clientes' => $this->clientes($actuales, $desde),
            'en_riesgo' => $this->enRiesgo(),
        ];
    }

    /**
     * @return Collection<int, Venta>
     */
    private function ventas(Carbon $desde, Carbon $hasta): Collection
    {
        return Venta::query()
            ->vigentes()
            ->whereBetween('pagada_en', [$desde, $hasta])
            ->get(['id', 'pagada_en', 'subtotal', 'descuento', 'propina', 'total', 'comision_monto', 'barbero_id', 'cliente_id', 'metodo_pago']);
    }

    /**
     * @return Collection<int, Cita>
     */
    private function citas(Carbon $desde, Carbon $hasta): Collection
    {
        return Cita::query()
            ->whereBetween('inicia_en', [$desde, $hasta])
            ->get(['id', 'inicia_en', 'termina_en', 'estado', 'origen', 'barbero_id', 'duracion_total_minutos']);
    }

    private function neto(Venta $v): int
    {
        return $v->subtotal - $v->descuento;
    }

    /**
     * Lo vendido cada día del periodo (servicios sin propinas), con ceros en los días sin ventas.
     *
     * @param  Collection<int, Venta>  $ventas
     * @return list<array{fecha: string, valor: int}>
     */
    private function ventasPorDia(Collection $ventas, Carbon $desde, int $dias): array
    {
        $porFecha = $ventas->groupBy(fn (Venta $v) => $v->pagada_en->toDateString())->map(fn ($g) => (int) $g->sum(fn ($v) => $this->neto($v)));

        return collect(range(0, $dias - 1))->map(function (int $i) use ($desde, $porFecha) {
            $fecha = $desde->copy()->addDays($i)->toDateString();

            return ['fecha' => $fecha, 'valor' => (int) ($porFecha[$fecha] ?? 0)];
        })->all();
    }

    /**
     * @param  Collection<int, Venta>  $actuales
     * @param  Collection<int, Venta>  $anteriores
     * @param  Collection<int, Cita>  $citasActuales
     * @param  Collection<int, Cita>  $citasAnteriores
     * @param  list<array{fecha: string, valor: int}>  $porDia
     * @return array<string, array<string, mixed>>
     */
    private function kpis(Collection $actuales, Collection $anteriores, Collection $citasActuales, Collection $citasAnteriores, Carbon $desde, Carbon $hasta, Carbon $antesDesde, Carbon $antesHasta, bool $manejaComisiones, array $porDia): array
    {
        $ventaActual = (int) $actuales->sum(fn ($v) => $this->neto($v));
        $ventaAnterior = (int) $anteriores->sum(fn ($v) => $this->neto($v));
        $atencionesActual = $actuales->count();
        $atencionesAnterior = $anteriores->count();
        $ticketActual = $atencionesActual ? (int) round($ventaActual / $atencionesActual) : 0;
        $ticketAnterior = $atencionesAnterior ? (int) round($ventaAnterior / $atencionesAnterior) : 0;

        $kpis = [
            'ventas' => $this->kpi($ventaActual, $ventaAnterior) + ['serie' => array_column($porDia, 'valor')],
            'atenciones' => $this->kpi($atencionesActual, $atencionesAnterior) + [
                'serie' => $this->atencionesPorDia($actuales, $desde, count($porDia)),
            ],
            'ticket' => $this->kpi($ticketActual, $ticketAnterior),
        ];

        $ocupacionActual = $this->ocupacion($citasActuales, $desde, $hasta);
        $ocupacionAnterior = $this->ocupacion($citasAnteriores, $antesDesde, $antesHasta);
        $kpis['ocupacion'] = [
            'valor' => $ocupacionActual,
            'anterior' => $ocupacionAnterior,
            'cambio' => $ocupacionActual !== null && $ocupacionAnterior !== null ? round($ocupacionActual - $ocupacionAnterior, 1) : null,
        ];

        [$tasaActual, $casos] = $this->inasistencia($citasActuales);
        [$tasaAnterior] = $this->inasistencia($citasAnteriores);
        $kpis['inasistencia'] = [
            'valor' => $tasaActual,
            'anterior' => $tasaAnterior,
            'cambio' => $tasaActual !== null && $tasaAnterior !== null ? round($tasaActual - $tasaAnterior, 1) : null,
            'casos' => $casos,
        ];

        if ($manejaComisiones) {
            $kpis['barberia'] = $this->kpi(
                $ventaActual - (int) $actuales->sum('comision_monto'),
                $ventaAnterior - (int) $anteriores->sum('comision_monto'),
            );
        }

        return $kpis;
    }

    /**
     * @return array{valor: int, anterior: int, cambio: float|null}
     */
    private function kpi(int $actual, int $anterior): array
    {
        return [
            'valor' => $actual,
            'anterior' => $anterior,
            // Sin periodo anterior con datos no hay porcentaje que calcular.
            'cambio' => $anterior > 0 ? round(($actual - $anterior) / $anterior * 100, 1) : null,
        ];
    }

    /**
     * @param  Collection<int, Venta>  $ventas
     * @return list<int>
     */
    private function atencionesPorDia(Collection $ventas, Carbon $desde, int $dias): array
    {
        $porFecha = $ventas->countBy(fn (Venta $v) => $v->pagada_en->toDateString());

        return collect(range(0, $dias - 1))
            ->map(fn (int $i) => (int) ($porFecha[$desde->copy()->addDays($i)->toDateString()] ?? 0))
            ->all();
    }

    /**
     * Qué parte del tiempo disponible de los barberos tuvo cita. Es una aproximación: usa el horario
     * semanal de hoy aplicado a todos los días del periodo.
     *
     * @param  Collection<int, Cita>  $citas
     */
    private function ocupacion(Collection $citas, Carbon $desde, Carbon $hasta): ?float
    {
        $minutosPorDia = Horario::query()
            ->whereIn('barbero_id', Barbero::query()->activos()->select('id'))
            ->get(['dia_semana', 'hora_inicio', 'hora_fin'])
            ->groupBy('dia_semana')
            ->map(fn ($franjas) => (int) $franjas->sum(
                fn (Horario $h) => Carbon::parse($h->hora_inicio)->diffInMinutes(Carbon::parse($h->hora_fin)),
            ));

        $disponibles = 0;
        for ($dia = $desde->copy()->startOfDay(); $dia->lte($hasta); $dia->addDay()) {
            $disponibles += $minutosPorDia[$dia->dayOfWeekIso] ?? 0;
        }

        if ($disponibles === 0) {
            return null;
        }

        $ocupados = (int) $citas
            ->filter(fn (Cita $c) => in_array($c->estado, EstadoCita::queOcupanCupo(), true))
            ->sum('duracion_total_minutos');

        return round(min(100, $ocupados / $disponibles * 100), 1);
    }

    /**
     * Porcentaje de citas ya resueltas en que el cliente no llegó.
     *
     * @param  Collection<int, Cita>  $citas
     * @return array{0: float|null, 1: int}
     */
    private function inasistencia(Collection $citas): array
    {
        $resueltas = $citas->filter(fn (Cita $c) => in_array($c->estado, [EstadoCita::Completada, EstadoCita::NoAsistio], true));
        $faltas = $resueltas->where('estado', EstadoCita::NoAsistio)->count();

        return [$resueltas->isEmpty() ? null : round($faltas / $resueltas->count() * 100, 1), $faltas];
    }

    /**
     * Cuántas citas hubo cada día de la semana a cada hora (todas las que ocuparon cupo o faltaron).
     *
     * @param  Collection<int, Cita>  $citas
     * @return array{horas: list<int>, dias: list<string>, celdas: list<list<int>>, maximo: int, total: int}
     */
    private function demanda(Collection $citas): array
    {
        $validas = $citas->filter(fn (Cita $c) => $c->estado !== EstadoCita::Cancelada);

        $horaMin = $validas->isEmpty() ? 8 : max(0, min(8, $validas->min(fn (Cita $c) => $c->inicia_en->hour)));
        $horaMax = $validas->isEmpty() ? 19 : min(23, max(19, $validas->max(fn (Cita $c) => $c->inicia_en->hour)));
        $horas = range($horaMin, $horaMax);

        $celdas = array_fill(0, 7, array_fill(0, count($horas), 0));
        foreach ($validas as $cita) {
            $celdas[$cita->inicia_en->dayOfWeekIso - 1][$cita->inicia_en->hour - $horaMin]++;
        }

        return [
            'horas' => $horas,
            'dias' => self::DIAS_SEMANA,
            'celdas' => $celdas,
            'maximo' => (int) collect($celdas)->flatten()->max(),
            'total' => $validas->count(),
        ];
    }

    /**
     * Los servicios que más dinero dejaron, por el nombre con que se cobraron.
     *
     * @param  Collection<int, Venta>  $ventas
     * @return list<array{nombre: string, cantidad: int, ingresos: int}>
     */
    private function topServicios(Collection $ventas): array
    {
        if ($ventas->isEmpty()) {
            return [];
        }

        return VentaItem::query()
            ->whereIn('venta_id', $ventas->pluck('id'))
            ->get(['descripcion', 'cantidad', 'precio_unitario'])
            ->groupBy('descripcion')
            ->map(fn ($items, string $nombre) => [
                'nombre' => $nombre,
                'cantidad' => (int) $items->sum('cantidad'),
                'ingresos' => (int) $items->sum(fn ($i) => $i->cantidad * $i->precio_unitario),
            ])
            ->sortByDesc('ingresos')
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Venta>  $ventas
     * @return list<array{nombre: string, atenciones: int, ventas: int, comision: int}>
     */
    private function porBarbero(Collection $ventas, bool $manejaComisiones): array
    {
        $nombres = Barbero::query()->withTrashed()->whereIn('id', $ventas->pluck('barbero_id')->unique())->pluck('nombre', 'id');

        return $ventas->groupBy('barbero_id')
            ->map(fn ($grupo, $barberoId) => [
                'nombre' => $nombres[$barberoId] ?? 'Barbero eliminado',
                'atenciones' => $grupo->count(),
                'ventas' => (int) $grupo->sum(fn ($v) => $this->neto($v)),
                'comision' => $manejaComisiones ? (int) $grupo->sum('comision_monto') : 0,
            ])
            ->sortByDesc('ventas')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Venta>  $ventas
     * @return list<array{etiqueta: string, total: int, cantidad: int}>
     */
    private function porMetodo(Collection $ventas): array
    {
        return $ventas->groupBy(fn (Venta $v) => $v->metodo_pago->value)
            ->map(fn ($grupo, string $metodo) => [
                'etiqueta' => MetodoPago::from($metodo)->etiqueta(),
                'total' => (int) $grupo->sum('total'),
                'cantidad' => $grupo->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Cita>  $citas
     * @return list<array{etiqueta: string, cantidad: int}>
     */
    private function porOrigen(Collection $citas): array
    {
        $etiquetas = [
            OrigenCita::Web->value => 'Enlace de reservas',
            OrigenCita::Whatsapp->value => 'WhatsApp',
            OrigenCita::Manual->value => 'Agendada por el personal',
        ];

        $conteo = $citas->filter(fn (Cita $c) => $c->estado !== EstadoCita::Cancelada)->countBy(fn (Cita $c) => $c->origen->value);

        return collect($etiquetas)
            ->map(fn (string $etiqueta, string $origen) => ['etiqueta' => $etiqueta, 'cantidad' => (int) ($conteo[$origen] ?? 0)])
            ->filter(fn (array $o) => $o['cantidad'] > 0)
            ->sortByDesc('cantidad')
            ->values()
            ->all();
    }

    /**
     * Clientes atendidos en el periodo: cuántos venían por primera vez y cuántos ya habían venido.
     * Las ventas de mostrador sin cliente registrado no entran.
     *
     * @param  Collection<int, Venta>  $ventas
     * @return array{nuevos: int, recurrentes: int, sin_registro: int}
     */
    private function clientes(Collection $ventas, Carbon $desde): array
    {
        $ids = $ventas->pluck('cliente_id')->filter()->unique();

        $yaHabianVenido = $ids->isEmpty() ? collect() : Venta::query()
            ->vigentes()
            ->where('pagada_en', '<', $desde)
            ->whereIn('cliente_id', $ids)
            ->distinct()
            ->pluck('cliente_id');

        return [
            'nuevos' => $ids->diff($yaHabianVenido)->count(),
            'recurrentes' => $ids->intersect($yaHabianVenido)->count(),
            'sin_registro' => $ventas->whereNull('cliente_id')->count(),
        ];
    }

    /**
     * Clientes habituales (2 o más visitas) que llevan tiempo sin venir y no tienen cita próxima.
     * Solo los que autorizaron contacto: son a quienes el dueño puede escribir.
     *
     * @return list<array{id: int, nombre: string, telefono: string, visitas: int, ultima: string, dias: int}>
     */
    private function enRiesgo(): array
    {
        $limite = now()->subDays(self::DIAS_EN_RIESGO)->toDateTimeString();

        $habituales = Venta::query()
            ->vigentes()
            ->whereNotNull('cliente_id')
            ->selectRaw('cliente_id, count(*) as visitas, max(pagada_en) as ultima')
            ->groupBy('cliente_id')
            ->havingRaw('count(*) >= 2')
            ->havingRaw('max(pagada_en) < ?', [$limite])
            ->get();

        if ($habituales->isEmpty()) {
            return [];
        }

        $ids = $habituales->pluck('cliente_id');

        $conCitaProxima = Cita::query()
            ->whereIn('cliente_id', $ids)
            ->where('inicia_en', '>', now())
            ->whereIn('estado', EstadoCita::queOcupanCupo())
            ->pluck('cliente_id');

        $clientes = Cliente::query()
            ->whereIn('id', $ids->diff($conCitaProxima))
            ->where('acepta_whatsapp', true)
            ->get(['id', 'nombre', 'telefono'])
            ->keyBy('id');

        return $habituales
            ->filter(fn ($h) => $clientes->has($h->cliente_id))
            ->map(function ($h) use ($clientes) {
                $ultima = Carbon::parse($h->ultima);

                return [
                    'id' => (int) $h->cliente_id,
                    'nombre' => $clientes[$h->cliente_id]->nombre,
                    'telefono' => $clientes[$h->cliente_id]->telefono,
                    'visitas' => (int) $h->visitas,
                    'ultima' => $ultima->toDateString(),
                    // Días de calendario: una visita del 21 de agosto, vista el 20 de octubre, es "hace 60 días" sin importar la hora.
                    'dias' => (int) $ultima->copy()->startOfDay()->diffInDays(now()->startOfDay()),
                ];
            })
            ->sortByDesc('visitas')
            ->take(8)
            ->values()
            ->all();
    }
}
