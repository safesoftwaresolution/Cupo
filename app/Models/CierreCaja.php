<?php

namespace App\Models;

use App\Models\Concerns\PerteneceABarberia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CierreCaja extends Model
{
    use PerteneceABarberia;

    protected $table = 'cierres_caja';

    protected $guarded = ['id', 'barberia_id'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }
}
