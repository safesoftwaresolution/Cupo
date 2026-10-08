<?php

namespace App\Models;

use App\Models\Concerns\PerteneceABarberia;
use Database\Factories\ServicioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Servicio extends Model
{
    /** @use HasFactory<ServicioFactory> */
    use HasFactory, PerteneceABarberia, SoftDeletes;

    protected $guarded = ['id', 'barberia_id'];

    protected function casts(): array
    {
        return [
            'duracion_minutos' => 'integer',
            'precio' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function barberos(): BelongsToMany
    {
        return $this->belongsToMany(Barbero::class, 'barbero_servicio')->withPivot('precio_especial');
    }

    public function scopeActivos($query)
    {
        return $query->where($this->qualifyColumn('activo'), true);
    }
}
