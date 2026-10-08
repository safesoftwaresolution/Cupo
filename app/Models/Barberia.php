<?php

namespace App\Models;

use Database\Factories\BarberiaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Barberia extends Model
{
    /** @use HasFactory<BarberiaFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'prueba_hasta' => 'datetime',
            'activa' => 'boolean',
        ];
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function enPrueba(): bool
    {
        return $this->prueba_hasta !== null && $this->prueba_hasta->isFuture();
    }
}
