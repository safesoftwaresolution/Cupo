<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCita;
use App\Models\Barbero;
use App\Models\Cita;
use App\Models\Horario;
use App\Models\Servicio;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
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
            'pasos' => $pasos,
            'urlReserva' => route('reserva.show', ['slug' => $request->user()->barberia->slug]),
            'hoy' => [
                'total' => $hoy->count(),
                'ingresos' => (int) $hoy->sum('precio_total'),
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
}
