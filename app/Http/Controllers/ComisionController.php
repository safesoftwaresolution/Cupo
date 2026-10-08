<?php

namespace App\Http\Controllers;

use App\Models\Barbero;
use App\Models\Venta;
use App\Support\Fechas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Liquidación de comisiones por barbero en un rango de fechas. Solo el dueño.
 */
class ComisionController extends Controller
{
    private const MAX_DIAS = 366;

    public function index(Request $request): Response
    {
        $this->exigirComisiones($request);

        [$desde, $hasta] = $this->rango($request);
        $filas = $this->filas($desde, $hasta);

        return Inertia::render('comisiones/Index', [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'filas' => $filas,
            'totales' => $this->totales($filas),
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $this->exigirComisiones($request);

        [$desde, $hasta] = $this->rango($request);
        $filas = $this->filas($desde, $hasta);
        $totales = $this->totales($filas);

        return response()->streamDownload(function () use ($filas, $totales) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF"); // BOM: para que Excel lea bien las tildes.

            // Punto y coma: es el separador que usa Excel con la configuración regional de Colombia.
            $escribir = fn (array $fila) => fputcsv($salida, $fila, ';');

            $escribir(['Barbero', 'Ventas', 'Servicios (sin descuentos)', 'Comisión', 'Propinas', 'Total a pagar al barbero', 'Para la barbería']);
            foreach ($filas as $f) {
                $escribir([$f['barbero'], $f['ventas'], $f['neto'], $f['comision'], $f['propinas'], $f['a_pagar'], $f['barberia']]);
            }
            $escribir(['TOTAL', $totales['ventas'], $totales['neto'], $totales['comision'], $totales['propinas'], $totales['a_pagar'], $totales['barberia']]);

            fclose($salida);
        }, "comisiones-{$desde->toDateString()}-a-{$hasta->toDateString()}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Una barbería que no maneja comisiones no tiene esta sección.
     */
    private function exigirComisiones(Request $request): void
    {
        abort_unless($request->user()->barberia->maneja_comisiones, 404);
    }

    /**
     * Por defecto, el mes en curso hasta hoy.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rango(Request $request): array
    {
        $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $desde = $request->filled('desde') ? Fechas::dia($request->query('desde')) : now()->startOfMonth();
        $hasta = $request->filled('hasta') ? Fechas::dia($request->query('hasta')) : now()->startOfDay();

        abort_if($desde->diffInDays($hasta) > self::MAX_DIAS, 422, 'El rango no puede superar un año.');

        return [$desde, $hasta];
    }

    /**
     * @return Collection<int, array{barbero: string, ventas: int, neto: int, comision: int, propinas: int, a_pagar: int, barberia: int}>
     */
    private function filas(Carbon $desde, Carbon $hasta): Collection
    {
        $filas = Venta::query()
            ->vigentes()
            ->whereBetween('pagada_en', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->selectRaw('barbero_id, count(*) as ventas, sum(subtotal - descuento) as neto, sum(comision_monto) as comision, sum(propina) as propinas')
            ->groupBy('barbero_id')
            ->get();

        $nombres = Barbero::query()->withTrashed()->whereIn('id', $filas->pluck('barbero_id'))->pluck('nombre', 'id');

        return $filas->map(fn ($f) => [
            'barbero' => $nombres[$f->barbero_id] ?? 'Barbero eliminado',
            'ventas' => (int) $f->ventas,
            'neto' => (int) $f->neto,
            'comision' => (int) $f->comision,
            'propinas' => (int) $f->propinas,
            'a_pagar' => (int) $f->comision + (int) $f->propinas,
            'barberia' => (int) $f->neto - (int) $f->comision,
        ])->sortByDesc('a_pagar')->values();
    }

    /**
     * @param  Collection<int, array<string, int|string>>  $filas
     * @return array<string, int>
     */
    private function totales(Collection $filas): array
    {
        return [
            'ventas' => (int) $filas->sum('ventas'),
            'neto' => (int) $filas->sum('neto'),
            'comision' => (int) $filas->sum('comision'),
            'propinas' => (int) $filas->sum('propinas'),
            'a_pagar' => (int) $filas->sum('a_pagar'),
            'barberia' => (int) $filas->sum('barberia'),
        ];
    }
}
