<?php

namespace App\Http\Middleware;

use App\Models\Barberia;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Páginas públicas de una barbería (`/b/{slug}`): no hay sesión, así que la
 * barbería activa se fija por el `slug` de la URL. Una barbería inactiva o
 * inexistente responde 404.
 */
class ResolverBarberiaPublica
{
    public function __construct(private TenantContext $contexto) {}

    public function handle(Request $request, Closure $next): Response
    {
        $barberia = Barberia::query()
            ->where('slug', $request->route('slug'))
            ->where('activa', true)
            ->firstOrFail();

        $request->attributes->set('barberia', $barberia);

        return $this->contexto->ejecutarEn($barberia, fn () => $next($request));
    }
}
