<?php

namespace Database\Seeders;

use App\Enums\EstadoCita;
use App\Enums\MetodoPago;
use App\Enums\OrigenCita;
use App\Models\Barberia;
use App\Models\Barbero;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Venta;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tres meses de historial realista para la barbería demo: citas, cobros, no-shows y clientes habituales.
 * Sirve para enseñar el panel con datos y para probar las gráficas.
 *
 *   php artisan db:seed --class=DatosDemoSeeder
 *
 * Es repetible: todo lo que crea lleva la marca MARCA en las notas del cliente, y al volver a correrlo
 * borra solo eso (sus citas y ventas incluidas) antes de generar de nuevo. No toca nada más.
 */
class DatosDemoSeeder extends Seeder
{
    public const MARCA = 'datos-demo';

    private const DIAS = 90;

    public function run(): void
    {
        $barberia = Barberia::query()->where('slug', 'demo')->first();

        if (! $barberia) {
            $this->command?->warn('No existe la barbería "demo". Corre primero: php artisan db:seed');

            return;
        }

        mt_srand(2026);
        fake()->seed(2026);

        app(TenantContext::class)->ejecutarEn($barberia, function () use ($barberia) {
            DB::transaction(function () use ($barberia) {
                $this->limpiar();
                $this->generar($barberia);
            });
        });
    }

    private function limpiar(): void
    {
        $clientes = Cliente::query()->where('notas', self::MARCA)->pluck('id');

        Venta::query()->whereIn('cliente_id', $clientes)->get()->each->delete();
        Cita::query()->whereIn('cliente_id', $clientes)->get()->each->delete();
        Cliente::query()->whereIn('id', $clientes)->get()->each->delete();
    }

    private function generar(Barberia $barberia): void
    {
        $barberos = Barbero::query()->activos()->with(['servicios', 'horarios'])->get()->filter(fn ($b) => $b->horarios->isNotEmpty() && $b->servicios->isNotEmpty());

        if ($barberos->isEmpty()) {
            $this->command?->warn('La barbería demo necesita barberos con horario y servicios.');

            return;
        }

        $habituales = $this->clientes(18);
        $ocasionales = $this->clientes(40);
        $ahora = now();

        for ($atras = self::DIAS - 1; $atras >= 0; $atras--) {
            $dia = $ahora->copy()->subDays($atras)->startOfDay();

            foreach ($barberos as $barbero) {
                foreach ($barbero->horarios->where('dia_semana', $dia->dayOfWeekIso) as $franja) {
                    $this->llenarFranja($barberia, $barbero, $dia, $franja->hora_inicio, $franja->hora_fin, $habituales, $ocasionales, $ahora);
                }
            }
        }

        $this->command?->info('Datos de demostración listos: '.Cita::query()->count().' citas y '.Venta::query()->count().' ventas.');
    }

    /**
     * @return list<Cliente>
     */
    private function clientes(int $cantidad): array
    {
        return collect(range(1, $cantidad))->map(fn () => Cliente::create([
            'nombre' => fake()->name(),
            'telefono' => '3'.fake()->unique()->numerify('#########'),
            'acepta_whatsapp' => mt_rand(1, 100) <= 85,
            'acepto_en' => now()->subDays(mt_rand(10, 120)),
            'notas' => self::MARCA,
        ]))->all();
    }

    /**
     * Recorre el horario del barbero y va poniendo citas seguidas con huecos al azar,
     * más llenas en las horas y días fuertes.
     *
     * @param  list<Cliente>  $habituales
     * @param  list<Cliente>  $ocasionales
     */
    private function llenarFranja(Barberia $barberia, Barbero $barbero, Carbon $dia, string $inicio, string $fin, array $habituales, array $ocasionales, Carbon $ahora): void
    {
        $cursor = $dia->copy()->setTimeFromTimeString($inicio);
        $limite = $dia->copy()->setTimeFromTimeString($fin);

        while ($cursor->lt($limite)) {
            $servicios = $this->servicios($barbero);
            $duracion = (int) $servicios->sum('duracion_minutos');

            if ($cursor->copy()->addMinutes($duracion)->gt($limite)) {
                break;
            }

            if (mt_rand(1, 1000) / 1000 <= $this->probabilidad($dia, $cursor)) {
                $this->crearCita($barberia, $barbero, $cursor->copy(), $servicios, $this->cliente($habituales, $ocasionales), $ahora);
                $cursor->addMinutes($duracion);
            } else {
                $cursor->addMinutes(mt_rand(1, 2) * 15); // hueco libre
            }
        }
    }

    /** Qué tan probable es que haya cita: sábados y viernes más, tardes más, mediodía menos. */
    private function probabilidad(Carbon $dia, Carbon $hora): float
    {
        $base = match ($dia->dayOfWeekIso) {
            6 => 0.88,
            5 => 0.78,
            1 => 0.5,
            default => 0.66,
        };

        $factorHora = match (true) {
            $hora->hour >= 16 && $hora->hour <= 18 => 1.15,
            $hora->hour === 12, $hora->hour === 13 => 0.7,
            $hora->hour <= 9 => 0.8,
            default => 1.0,
        };

        return min(0.95, $base * $factorHora);
    }

    private function servicios(Barbero $barbero)
    {
        $todos = $barbero->servicios->where('activo', true)->values();
        $cuantos = mt_rand(1, 100) <= 30 && $todos->count() > 1 ? 2 : 1;

        return $todos->shuffle()->take($cuantos)->values();
    }

    /**
     * @param  list<Cliente>  $habituales
     * @param  list<Cliente>  $ocasionales
     */
    private function cliente(array $habituales, array $ocasionales): Cliente
    {
        $azar = mt_rand(1, 100);

        return match (true) {
            $azar <= 45 => $habituales[array_rand($habituales)],
            $azar <= 80 => $ocasionales[array_rand($ocasionales)],
            default => $this->clientes(1)[0], // alguien que viene por primera vez
        };
    }

    private function crearCita(Barberia $barberia, Barbero $barbero, Carbon $inicio, $servicios, Cliente $cliente, Carbon $ahora): void
    {
        $duracion = (int) $servicios->sum('duracion_minutos');
        $fin = $inicio->copy()->addMinutes($duracion);
        $precio = (int) $servicios->sum(fn ($s) => $s->pivot->precio_especial ?? $s->precio);
        $paso = $fin->lte($ahora);

        $estado = match (true) {
            ! $paso => mt_rand(1, 100) <= 60 ? EstadoCita::Confirmada : EstadoCita::Pendiente,
            default => $this->estadoPasado(),
        };

        $cita = Cita::create([
            'codigo' => Str::lower(Str::random(10)),
            'barbero_id' => $barbero->id,
            'cliente_id' => $cliente->id,
            'inicia_en' => $inicio,
            'termina_en' => $fin,
            'estado' => $estado,
            'origen' => $this->origen(),
            'precio_total' => $precio,
            'duracion_total_minutos' => $duracion,
            'confirmada_en' => in_array($estado, [EstadoCita::Confirmada, EstadoCita::Completada], true) ? $inicio->copy()->subHours(mt_rand(2, 20)) : null,
            'cancelada_en' => $estado === EstadoCita::Cancelada ? $inicio->copy()->subHours(mt_rand(3, 30)) : null,
            'motivo_cancelacion' => $estado === EstadoCita::Cancelada ? 'Cancelada por el cliente' : null,
        ]);

        $cita->servicios()->attach($servicios->mapWithKeys(fn ($s) => [$s->id => [
            'nombre' => $s->nombre,
            'duracion_minutos' => $s->duracion_minutos,
            'precio' => $s->pivot->precio_especial ?? $s->precio,
        ]])->all());

        if ($estado === EstadoCita::Completada) {
            $this->cobrar($barberia, $barbero, $cita, $cliente, $servicios, $fin);
        }
    }

    private function estadoPasado(): EstadoCita
    {
        return match (true) {
            ($azar = mt_rand(1, 100)) <= 8 => EstadoCita::Cancelada,
            $azar <= 14 => EstadoCita::NoAsistio,
            default => EstadoCita::Completada,
        };
    }

    private function origen(): OrigenCita
    {
        return match (true) {
            ($azar = mt_rand(1, 100)) <= 36 => OrigenCita::Web,
            $azar <= 48 => OrigenCita::Whatsapp,
            default => OrigenCita::Manual,
        };
    }

    private function cobrar(Barberia $barberia, Barbero $barbero, Cita $cita, Cliente $cliente, $servicios, Carbon $cuando): void
    {
        $subtotal = $cita->precio_total;
        $descuento = mt_rand(1, 100) <= 8 ? [1000, 2000, 3000][mt_rand(0, 2)] : 0;
        $propina = mt_rand(1, 100) <= 28 ? [1000, 2000, 3000, 5000][mt_rand(0, 3)] : 0;
        $porcentaje = $barberia->maneja_comisiones ? $barbero->comision_porcentaje : 0;

        $venta = Venta::create([
            'cita_id' => $cita->id,
            'barbero_id' => $barbero->id,
            'cliente_id' => $cliente->id,
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'propina' => $propina,
            'total' => $subtotal - $descuento + $propina,
            'metodo_pago' => $this->metodo(),
            'comision_porcentaje' => $porcentaje,
            'comision_monto' => Venta::calcularComision($subtotal, $descuento, $porcentaje),
            'pagada_en' => $cuando,
        ]);

        $venta->items()->createMany($servicios->map(fn ($s) => [
            'servicio_id' => $s->id,
            'descripcion' => $s->nombre,
            'cantidad' => 1,
            'precio_unitario' => $s->pivot->precio_especial ?? $s->precio,
        ])->all());
    }

    private function metodo(): MetodoPago
    {
        return match (true) {
            ($azar = mt_rand(1, 100)) <= 42 => MetodoPago::Efectivo,
            $azar <= 72 => MetodoPago::Nequi,
            $azar <= 80 => MetodoPago::Daviplata,
            $azar <= 90 => MetodoPago::Tarjeta,
            default => MetodoPago::Transferencia,
        };
    }
}
