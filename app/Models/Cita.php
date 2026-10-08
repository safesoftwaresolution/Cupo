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
        return $this->belongsToMany(Servicio::class, 'cita_servicio')
            ->withPivot(['nombre', 'duracion_minutos', 'precio']);
    }

    public function venta(): HasOne
    {
        return $this->hasOne(Venta::class);
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
