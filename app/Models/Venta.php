<?php

namespace App\Models;

use App\Enums\MetodoPago;
use App\Models\Concerns\PerteneceABarberia;
use Database\Factories\VentaFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    /** @use HasFactory<VentaFactory> */
    use HasFactory, PerteneceABarberia;

    protected $guarded = ['id', 'barberia_id'];

    protected function casts(): array
    {
        return [
            'metodo_pago' => MetodoPago::class,
            'comision_porcentaje' => 'decimal:2',
            'pagada_en' => 'datetime',
            'anulada_en' => 'datetime',
        ];
    }

    /**
     * Ventas que cuentan en la caja y en las comisiones (no anuladas).
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull($this->qualifyColumn('anulada_en'));
    }

    public function scopeDelDia(Builder $query, CarbonInterface $fecha): Builder
    {
        return $query->whereBetween($this->qualifyColumn('pagada_en'), [$fecha->copy()->startOfDay(), $fecha->copy()->endOfDay()]);
    }

    public function estaAnulada(): bool
    {
        return $this->anulada_en !== null;
    }

    /**
     * Comisión del barbero en pesos: porcentaje sobre (subtotal - descuento).
     * La propina no entra en la base porque va completa para el barbero.
     */
    public static function calcularComision(int $subtotal, int $descuento, float|string $porcentaje): int
    {
        $base = max(0, $subtotal - $descuento);

        return (int) round($base * (float) $porcentaje / 100);
    }

    public function items(): HasMany
    {
        return $this->hasMany(VentaItem::class);
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function barbero(): BelongsTo
    {
        return $this->belongsTo(Barbero::class)->withTrashed();
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cierreCaja(): BelongsTo
    {
        return $this->belongsTo(CierreCaja::class);
    }
}
