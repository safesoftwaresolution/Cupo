<?php

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Aísla los datos por barbería. Sin barbería activa no devuelve filas:
 * es preferible una lista vacía a mostrar datos de otro negocio.
 */
class BarberiaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $barberiaId = app(TenantContext::class)->id();

        if ($barberiaId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('barberia_id'), $barberiaId);
    }
}
