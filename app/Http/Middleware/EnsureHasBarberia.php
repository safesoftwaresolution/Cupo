<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El panel solo existe dentro de una barbería. Una cuenta sin barbería no ve datos de nadie.
 */
class EnsureHasBarberia
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->barberia_id, 403, 'Tu cuenta no está asociada a ninguna barbería.');

        return $next($request);
    }
}
