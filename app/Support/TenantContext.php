<?php

namespace App\Support;

use App\Models\Barberia;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Barbería "activa" de la petición actual.
 *
 * Por defecto sale del usuario autenticado. Los contextos sin sesión
 * (reserva pública, webhooks de WhatsApp, colas, comandos) deben fijarla
 * de forma explícita con `definir()` o `ejecutarEn()`.
 */
class TenantContext
{
    private ?int $barberiaId = null;

    public function id(): ?int
    {
        return $this->barberiaId ?? Auth::user()?->barberia_id;
    }

    public function definir(Barberia|int|null $barberia): void
    {
        $this->barberiaId = $barberia instanceof Barberia ? $barberia->getKey() : $barberia;
    }

    public function olvidar(): void
    {
        $this->barberiaId = null;
    }

    /**
     * Ejecuta el callback dentro de una barbería y restaura el contexto anterior.
     */
    public function ejecutarEn(Barberia|int $barberia, Closure $callback): mixed
    {
        $anterior = $this->barberiaId;
        $this->definir($barberia);

        try {
            return $callback();
        } finally {
            $this->barberiaId = $anterior;
        }
    }
}
