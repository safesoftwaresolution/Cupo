<?php

namespace App\Models;

use App\Models\Concerns\PerteneceABarberia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bloqueo extends Model
{
    use PerteneceABarberia;

    protected $table = 'bloqueos';

    protected $guarded = ['id', 'barberia_id'];

    protected function casts(): array
    {
        return [
            'inicia_en' => 'datetime',
            'termina_en' => 'datetime',
        ];
    }

    public function barbero(): BelongsTo
    {
        return $this->belongsTo(Barbero::class);
    }
}
