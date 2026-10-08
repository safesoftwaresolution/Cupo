<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServicioRequest;
use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServicioController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('servicios/Index', [
            'servicios' => Servicio::query()
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'descripcion', 'duracion_minutos', 'precio', 'activo']),
        ]);
    }

    public function store(ServicioRequest $request): RedirectResponse
    {
        Servicio::create([
            ...$request->validated(),
            'orden' => (Servicio::query()->max('orden') ?? -1) + 1,
        ]);

        return back()->with('success', 'Servicio creado.');
    }

    public function update(ServicioRequest $request, Servicio $servicio): RedirectResponse
    {
        $servicio->update($request->validated());

        return back()->with('success', 'Servicio actualizado.');
    }

    public function destroy(Servicio $servicio): RedirectResponse
    {
        // Las citas y ventas ya hechas conservan su copia del nombre y precio.
        $servicio->delete();

        return back()->with('success', 'Servicio eliminado.');
    }
}
