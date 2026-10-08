<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCita;
use App\Http\Requests\BarberoRequest;
use App\Models\Barbero;
use App\Models\Cita;
use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BarberoController extends Controller
{
    public function index(): Response
    {
        $barberos = Barbero::query()
            ->with('servicios:id')
            ->withCount('horarios')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Barbero $b) => [
                'id' => $b->id,
                'nombre' => $b->nombre,
                'telefono' => $b->telefono,
                'comision_porcentaje' => (float) $b->comision_porcentaje,
                'color' => $b->color,
                'activo' => $b->activo,
                'servicios' => $b->servicios->pluck('id')->all(),
                'franjas' => $b->horarios_count,
            ]);

        return Inertia::render('barberos/Index', [
            'barberos' => $barberos,
            'servicios' => Servicio::query()->activos()->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(BarberoRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $barbero = Barbero::create($this->atributos($datos));
        $barbero->servicios()->sync($datos['servicios']);

        return back()->with('success', 'Barbero creado. Ahora define su horario.');
    }

    public function update(BarberoRequest $request, Barbero $barbero): RedirectResponse
    {
        $datos = $request->validated();

        $barbero->update($this->atributos($datos));
        $barbero->servicios()->sync($datos['servicios']);

        return back()->with('success', 'Barbero actualizado.');
    }

    public function destroy(Barbero $barbero): RedirectResponse
    {
        $tieneCitasFuturas = Cita::query()
            ->where('barbero_id', $barbero->id)
            ->whereIn('estado', EstadoCita::queOcupanCupo())
            ->where('inicia_en', '>', now())
            ->exists();

        if ($tieneCitasFuturas) {
            return back()->withErrors([
                'barbero' => 'Este barbero tiene citas pendientes. Cancélalas o reasígnalas antes de eliminarlo.',
            ]);
        }

        $barbero->delete();

        return back()->with('success', 'Barbero eliminado.');
    }

    /**
     * Los campos del barbero sin la lista de servicios. Si no se envió comisión (la barbería no
     * las maneja) se conserva la que ya tenía, para no perderla si luego se activan.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function atributos(array $datos): array
    {
        return collect($datos)
            ->except('servicios')
            ->reject(fn ($valor, $campo) => $campo === 'comision_porcentaje' && $valor === null)
            ->all();
    }
}
