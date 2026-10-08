<?php

namespace App\Http\Controllers;

use App\Models\MensajeWhatsapp;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registro de los mensajes de WhatsApp que envió Cupo, para saber qué pasó con cada uno.
 */
class MensajeController extends Controller
{
    public function index(): Response
    {
        $mensajes = MensajeWhatsapp::query()
            ->with(['cliente:id,nombre', 'cita:id,inicia_en'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (MensajeWhatsapp $m) => [
                'id' => $m->id,
                'cliente' => $m->cliente?->nombre ?? 'Cliente eliminado',
                'telefono' => $m->telefono,
                'cita' => $m->cita?->inicia_en->toIso8601String(),
                'creado' => $m->created_at->toIso8601String(),
                'estado' => $m->estado->value,
                'estado_etiqueta' => $m->estado->etiqueta(),
                'respuesta' => $m->respuesta,
                'error' => $m->error,
            ]);

        return Inertia::render('mensajes/Index', [
            'mensajes' => $mensajes,
            // En modo de pruebas nada sale de verdad: la pantalla lo dice para que nadie se confunda.
            'modoPrueba' => config('whatsapp.driver') !== 'cloud',
        ]);
    }
}
