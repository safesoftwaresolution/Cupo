<?php

namespace App\Models;

use App\Models\Concerns\PerteneceABarberia;
use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory, PerteneceABarberia;

    protected $guarded = ['id', 'barberia_id'];

    protected function casts(): array
    {
        return [
            'acepta_whatsapp' => 'boolean',
            'acepto_en' => 'datetime',
        ];
    }

    /**
     * Guarda el celular solo con dígitos e indicativo de Colombia (57),
     * que es el formato que espera la API de WhatsApp.
     */
    protected function telefono(): Attribute
    {
        return Attribute::set(fn (string $valor) => self::normalizarTelefono($valor));
    }

    public static function normalizarTelefono(string $valor): string
    {
        $digitos = preg_replace('/\D+/', '', $valor);

        // Celular colombiano sin indicativo: 10 dígitos que empiezan por 3.
        if (strlen($digitos) === 10 && $digitos[0] === '3') {
            return '57'.$digitos;
        }

        return $digitos;
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function scopeBuscar($query, ?string $texto)
    {
        if ($texto === null || trim($texto) === '') {
            return $query;
        }

        $digitos = preg_replace('/\D+/', '', $texto);

        return $query->where(function ($q) use ($texto, $digitos) {
            $q->where('nombre', 'like', '%'.trim($texto).'%');

            if ($digitos !== '') {
                $q->orWhere('telefono', 'like', '%'.$digitos.'%');
            }
        });
    }
}
