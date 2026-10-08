<?php

namespace App\Models;

use App\Models\Concerns\PerteneceABarberia;
use Database\Factories\BarberoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Barbero extends Model
{
    /** @use HasFactory<BarberoFactory> */
    use HasFactory, PerteneceABarberia, SoftDeletes;

    protected $guarded = ['id', 'barberia_id'];

    protected function casts(): array
    {
        return [
            'comision_porcentaje' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function servicios(): BelongsToMany
    {
        return $this->belongsToMany(Servicio::class, 'barbero_servicio')->withPivot('precio_especial');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }

    public function bloqueos(): HasMany
    {
        return $this->hasMany(Bloqueo::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    public function scopeActivos($query)
    {
        return $query->where($this->qualifyColumn('activo'), true);
    }
}
