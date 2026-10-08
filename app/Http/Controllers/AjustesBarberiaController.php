<?php

namespace App\Http\Controllers;

use App\Http\Requests\AjustesBarberiaRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ajustes de la barbería del usuario. Solo el dueño.
 */
class AjustesBarberiaController extends Controller
{
    public function edit(Request $request): Response
    {
        $barberia = $request->user()->barberia;

        return Inertia::render('ajustes/Barberia', [
            'ajustes' => $barberia->only([
                'nombre', 'telefono', 'direccion', 'ciudad', 'slug',
                'intervalo_agenda', 'anticipacion_minima', 'dias_reserva',
                'maneja_comisiones', 'recordatorios_activos', 'recordatorio_horas',
            ]),
            'opciones' => [
                'intervalos' => AjustesBarberiaRequest::INTERVALOS,
                'anticipaciones' => AjustesBarberiaRequest::ANTICIPACIONES,
                'horasRecordatorio' => AjustesBarberiaRequest::HORAS_RECORDATORIO,
            ],
            'urlReserva' => route('reserva.show', ['slug' => $barberia->slug]),
        ]);
    }

    public function update(AjustesBarberiaRequest $request): RedirectResponse
    {
        $request->user()->barberia->update($request->validated());

        return back()->with('success', 'Ajustes guardados.');
    }
}
