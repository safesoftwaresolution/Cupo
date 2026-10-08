<?php

namespace App\Http\Controllers;

use App\Http\Requests\HorarioRequest;
use App\Models\Barbero;
use App\Models\Horario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HorarioController extends Controller
{
    public function edit(Barbero $barbero): Response
    {
        $franjas = $barbero->horarios()
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->get()
            ->map(fn (Horario $h) => [
                'dia_semana' => $h->dia_semana,
                'hora_inicio' => substr($h->hora_inicio, 0, 5),
                'hora_fin' => substr($h->hora_fin, 0, 5),
            ]);

        return Inertia::render('barberos/Horario', [
            'barbero' => ['id' => $barbero->id, 'nombre' => $barbero->nombre],
            'franjas' => $franjas,
        ]);
    }

    /**
     * Reemplaza el horario semanal completo: lo que llega es lo que queda.
     */
    public function update(HorarioRequest $request, Barbero $barbero): RedirectResponse
    {
        DB::transaction(function () use ($request, $barbero) {
            $barbero->horarios()->delete();

            foreach ($request->validated('franjas') as $franja) {
                $barbero->horarios()->create([
                    'dia_semana' => $franja['dia_semana'],
                    'hora_inicio' => $franja['hora_inicio'],
                    'hora_fin' => $franja['hora_fin'],
                ]);
            }
        });

        return back()->with('success', 'Horario guardado.');
    }
}
