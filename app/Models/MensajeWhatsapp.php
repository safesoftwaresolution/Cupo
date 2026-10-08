<?php

namespace App\Models;

use App\Enums\EstadoMensaje;
use App\Models\Concerns\PerteneceABarberia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensajeWhatsapp extends Model
{
    use PerteneceABarberia;

    public const RECORDATORIO = 'recordatorio';

    protected $table = 'mensajes_whatsapp';

    protected $guarded = ['id', 'barberia_id'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoMensaje::class,
            'enviado_en' => 'datetime',
            'respondido_en' => 'datetime',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Avanza el estado solo hacia adelante: un aviso tardío de "enviado" no pisa a "leído".
     */
    public function avanzarA(EstadoMensaje $nuevo): void
    {
        if ($nuevo->rango() > $this->estado->rango()) {
            $this->update(['estado' => $nuevo]);
        }
    }
}
