<?php

namespace App\Models;

use App\Enums\EstadoCita;
use App\Enums\OrigenCita;
use App\Models\Concerns\PerteneceABarberia;
use Carbon\CarbonInterface;
use Database\Factories\CitaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cita extends Model
{
    /** @use HasFactory<CitaFactory> */
    use HasFactory, PerteneceABarberia;

    protected $guarded = ['id', 'barberia_id'];

    protected function casts(): array
    {
        return [
            'inicia_en' => 'datetime',
            'termina_en' => 'datetime',
            'estado' => EstadoCita::class,
            'origen' => OrigenCita::class,
            'confirmada_en' => 'datetime',
            'cancelada_en' => 'datetime',
            'recordatorio_enviado_en' => 'datetime',
        ];
    }

    public function barbero(): BelongsTo
    {
        return $this->belongsTo(Barbero::class)->withTrashed();
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function servicios(): BelongsToMany
    {
        // `withTrashed`: si el servicio se eliminó después, la cita conserva su copia (nombre y precio).
        return $this->belongsToMany(Servicio::class, 'cita_servicio')
            ->withTrashed()
            ->withPivot(['nombre', 'duracion_minutos', 'precio']);
    }

    public function recordatorio(): HasOne
    {
        return $this->hasOne(MensajeWhatsapp::class)->where('mensajes_whatsapp.tipo', MensajeWhatsapp::RECORDATORIO);
    }

    /**
     * La venta vigente de la cita (las anuladas no cuentan, así que puede cobrarse de nuevo).
     */
    public function venta(): HasOne
    {
        return $this->hasOne(Venta::class)->whereNull('ventas.anulada_en');
    }

    /**
     * El cliente puede cancelar solo mientras la cita siga viva y no haya empezado.
     */
    public function cancelablePorCliente(): bool
    {
        return in_array($this->estado, [EstadoCita::Pendiente, EstadoCita::Confirmada], true)
            && $this->inicia_en->isFuture();
    }

    /**
     * Citas que ocupan cupo y se cruzan con el rango [$inicio, $fin) del barbero.
     * Dos citas pegadas (una termina cuando la otra empieza) no se cruzan.
     */
    public function scopeSolapadas(Builder $query, int $barberoId, CarbonInterface $inicio, CarbonInterface $fin): Builder
    {
        return $query
            ->where($this->qualifyColumn('barbero_id'), $barberoId)
            ->whereIn($this->qualifyColumn('estado'), EstadoCita::queOcupanCupo())
            ->where($this->qualifyColumn('inicia_en'), '<', $fin)
            ->where($this->qualifyColumn('termina_en'), '>', $inicio);
    }
}
