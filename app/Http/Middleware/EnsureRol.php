<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: `->middleware('rol:dueno,recepcion')`.
 */
class EnsureRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $rol = $request->user()?->rol?->value;

        abort_unless($rol !== null && in_array($rol, $roles, true), 403, 'No tienes permiso para esta sección.');

        return $next($request);
    }
}
