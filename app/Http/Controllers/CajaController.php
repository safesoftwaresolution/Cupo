<?php

namespace App\Http\Controllers;

use App\Actions\AnularVenta;
use App\Actions\CerrarCaja;
use App\Actions\CobrarCita;
use App\Actions\VenderServicios;
use App\Enums\EstadoCita;
use App\Enums\MetodoPago;
use App\Exceptions\CobroNoPermitido;
use App\Http\Requests\CobroRequest;
use App\Models\Barbero;
use App\Models\CierreCaja;
use App\Models\Cita;
use App\Models\Servicio;
use App\Models\Venta;
use App\Support\Fechas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CajaController extends Controller
{
    public function index(Request $request): Response
    {
        $fecha = Fechas::dia($request->query('fecha'));

        $ventas = Venta::query()
            ->delDia($fecha)
            ->with(['barbero', 'cliente:id,nombre', 'items'])
            ->orderByDesc('pagada_en')
            ->get();

        $vigentes = $ventas->whereNull('anulada_en');
        $neto = fn ($v) => $v->subtotal - $v->descuento;

        $cierre = CierreCaja::query()->with('usuario:id,name')->whereDate('fecha', $fecha->toDateString())->first();

        $porCobrar = Cita::query()
            ->whereBetween('inicia_en', [$fecha->copy()->startOfDay(), $fecha->copy()->endOfDay()])
            ->whereIn('estado', [EstadoCita::Pendiente, EstadoCita::Confirmada, EstadoCita::Completada])
            ->whereDoesntHave('venta')
            ->with(['cliente:id,nombre', 'barbero', 'servicios'])
            ->orderBy('inicia_en')
            ->get();

        return Inertia::render('caja/Index', [
            'fecha' => $fecha->toDateString(),
            'hoy' => now()->toDateString(),
            'resumen' => [
                'ventas' => $vigentes->count(),
                'total' => (int) $vigentes->sum('total'),
                'servicios' => (int) $vigentes->sum($neto),
                'propinas' => (int) $vigentes->sum('propina'),
                'comisiones' => (int) $vigentes->sum('comision_monto'),
                'barberia' => (int) ($vigentes->sum($neto) - $vigentes->sum('comision_monto')),
                'efectivo_esperado' => (int) $vigentes->where('metodo_pago', MetodoPago::Efectivo)->sum('total'),
            ],
            'porMetodo' => collect(MetodoPago::cases())->map(fn (MetodoPago $m) => [
                'valor' => $m->value,
                'etiqueta' => $m->etiqueta(),
                'cantidad' => $vigentes->where('metodo_pago', $m)->count(),
                'total' => (int) $vigentes->where('metodo_pago', $m)->sum('total'),
            ])->values(),
            'porBarbero' => $vigentes->groupBy('barbero_id')->map(fn ($grupo) => [
                'nombre' => $grupo->first()->barbero->nombre,
                'ventas' => $grupo->count(),
                'servicios' => (int) $grupo->sum($neto),
                'comision' => (int) $grupo->sum('comision_monto'),
                'propinas' => (int) $grupo->sum('propina'),
                'a_pagar' => (int) ($grupo->sum('comision_monto') + $grupo->sum('propina')),
            ])->sortByDesc('a_pagar')->values(),
            'ventas' => $ventas->map(fn (Venta $v) => [
                'id' => $v->id,
                'hora' => $v->pagada_en->format('H:i'),
                'cliente' => $v->cliente?->nombre,
                'barbero' => $v->barbero->nombre,
                'servicios' => $v->items->pluck('descripcion')->join(', '),
                'metodo' => $v->metodo_pago->value,
                'metodo_etiqueta' => $v->metodo_pago->etiqueta(),
                'subtotal' => $v->subtotal,
                'descuento' => $v->descuento,
                'propina' => $v->propina,
                'total' => $v->total,
                'comision' => $v->comision_monto,
                'anulada' => $v->estaAnulada(),
                'motivo_anulacion' => $v->motivo_anulacion,
            ])->values(),
            'porCobrar' => $porCobrar->map(fn (Cita $c) => $this->citaParaCobro($c))->values(),
            'cierre' => $cierre ? [
                'id' => $cierre->id,
                'efectivo_esperado' => $cierre->efectivo_esperado,
                'efectivo_contado' => $cierre->efectivo_contado,
                'diferencia' => $cierre->diferencia,
                'notas' => $cierre->notas,
                'usuario' => $cierre->usuario?->name,
            ] : null,
            'barberos' => Barbero::query()->activos()->with('servicios:id')->orderBy('nombre')->get()->map(fn (Barbero $b) => [
                'id' => $b->id,
                'nombre' => $b->nombre,
                'servicios' => $b->servicios->pluck('id')->all(),
            ]),
            'servicios' => Servicio::query()->activos()->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre', 'precio']),
            'metodos' => collect(MetodoPago::cases())->map(fn (MetodoPago $m) => ['valor' => $m->value, 'etiqueta' => $m->etiqueta()]),
        ]);
    }

    public function cobrarCita(CobroRequest $request, Cita $cita, CobrarCita $cobrar): RedirectResponse
    {
        try {
            $venta = $cobrar($cita, $request->descuento(), $request->propina(), $request->metodo(), $request->user());
        } catch (CobroNoPermitido $e) {
            return back()->withErrors(['cobro' => $e->getMessage()]);
        }

        return back()->with('success', 'Cobro registrado: '.$this->pesos($venta->total).'.');
    }

    public function storeVenta(CobroRequest $request, VenderServicios $vender): RedirectResponse
    {
        $barbero = Barbero::query()->activos()->findOrFail($request->integer('barbero_id'));

        try {
            $venta = $vender(
                $barbero,
                array_map('intval', $request->input('servicios')),
                $request->descuento(),
                $request->propina(),
                $request->metodo(),
                $request->user(),
            );
        } catch (CobroNoPermitido $e) {
            return back()->withErrors(['cobro' => $e->getMessage()]);
        }

        return back()->with('success', 'Venta registrada: '.$this->pesos($venta->total).'.');
    }

    public function anular(Request $request, Venta $venta, AnularVenta $anular): RedirectResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:255']]);

        try {
            $anular($venta, $datos['motivo'], $request->user());
        } catch (CobroNoPermitido $e) {
            return back()->withErrors(['anulacion' => $e->getMessage()]);
        }

        return back()->with('success', 'Venta anulada.');
    }

    public function cerrar(Request $request, CerrarCaja $cerrar): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'efectivo_contado' => ['required', 'integer', 'between:0,1000000000'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $cierre = $cerrar(Fechas::dia($datos['fecha']), (int) $datos['efectivo_contado'], $datos['notas'] ?? null, $request->user());
        } catch (CobroNoPermitido $e) {
            return back()->withErrors(['cierre' => $e->getMessage()]);
        }

        $mensaje = match (true) {
            $cierre->diferencia === 0 => 'Caja cerrada. El efectivo cuadra exacto.',
            $cierre->diferencia > 0 => 'Caja cerrada. Sobran '.$this->pesos($cierre->diferencia).'.',
            default => 'Caja cerrada. Faltan '.$this->pesos(abs($cierre->diferencia)).'.',
        };

        return back()->with('success', $mensaje);
    }

    public function reabrir(CierreCaja $cierre): RedirectResponse
    {
        // Las ventas del día vuelven a quedar abiertas a cambios.
        Venta::query()->where('cierre_caja_id', $cierre->id)->update(['cierre_caja_id' => null]);
        $cierre->delete();

        return back()->with('success', 'Caja reabierta.');
    }

    /**
     * @return array<string, mixed>
     */
    private function citaParaCobro(Cita $cita): array
    {
        return [
            'id' => $cita->id,
            'hora' => $cita->inicia_en->format('H:i'),
            'cliente' => $cita->cliente->nombre,
            'barbero' => $cita->barbero->nombre,
            'servicios' => $cita->servicios->pluck('pivot.nombre')->join(', '),
            'precio_total' => $cita->precio_total,
        ];
    }

    private function pesos(int $valor): string
    {
        return '$ '.number_format($valor, 0, ',', '.');
    }
}
