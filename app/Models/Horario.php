<?php

namespace App\Models;

use App\Models\Concerns\PerteneceABarberia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Horario extends Model
{
    use PerteneceABarberia;

    protected $guarded = ['id', 'barberia_id'];

    public function barbero(): BelongsTo
    {
        return $this->belongsTo(Barbero::class);
    }
}
