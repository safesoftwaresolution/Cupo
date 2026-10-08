<?php

namespace App\Models\Concerns;

use App\Models\Barberia;
use App\Models\Scopes\BarberiaScope;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait PerteneceABarberia
{
    public static function bootPerteneceABarberia(): void
    {
        static::addGlobalScope(new BarberiaScope);

        static::creating(function ($modelo) {
            if ($modelo->barberia_id === null) {
                $modelo->barberia_id = app(TenantContext::class)->id();
            }
        });
    }

    public function barberia(): BelongsTo
    {
        return $this->belongsTo(Barberia::class);
    }
}
