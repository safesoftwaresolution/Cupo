<?php

namespace App\Console\Commands;

use App\Enums\EstadoCita;
use App\Enums\EstadoMensaje;
use App\Jobs\EnviarRecordatorioCita;
use App\Models\Barberia;
use App\Models\Cita;
use App\Models\MensajeWhatsapp;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;

class EnviarRecordatorios extends Command
{
    protected $signature = 'recordatorios:enviar';

    protected $description = 'Programa los recordatorios de WhatsApp de las citas que están por llegar.';

    /** No se avisa a quien ya casi llega: un recordatorio con menos de esto de margen no sirve. */
    private const MARGEN_MINIMO_MINUTOS = 30;

    public function handle(TenantContext $tenant): int
    {
        $programados = 0;

        Barberia::query()
            ->where('activa', true)
            ->where('recordatorios_activos', true)
            ->each(function (Barberia $barberia) use ($tenant, &$programados) {
                $programados += $tenant->ejecutarEn($barberia, fn () => $this->programar($barberia));
            });

        $this->info("Recordatorios programados: {$programados}");

        return self::SUCCESS;
    }

    private function programar(Barberia $barberia): int
    {
        $citas = Cita::query()
            ->whereIn('estado', [EstadoCita::Pendiente, EstadoCita::Confirmada])
            ->whereBetween('inicia_en', [now()->addMinutes(self::MARGEN_MINIMO_MINUTOS), now()->addHours($barberia->recordatorio_horas)])
            ->whereDoesntHave('recordatorio')
            ->whereHas('cliente', fn ($q) => $q->where('acepta_whatsapp', true))
            ->with('cliente')
            ->get();

        $programados = 0;

        foreach ($citas as $cita) {
            try {
                // La fila única (cita, tipo) es la que garantiza un solo recordatorio por cita,
                // incluso si dos ejecuciones del comando se cruzan.
                $mensaje = MensajeWhatsapp::create([
                    'cita_id' => $cita->id,
                    'cliente_id' => $cita->cliente_id,
                    'tipo' => MensajeWhatsapp::RECORDATORIO,
                    'telefono' => $cita->cliente->telefono,
                    'estado' => EstadoMensaje::Pendiente,
                ]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            EnviarRecordatorioCita::dispatch($mensaje->id, $barberia->id);
            $programados++;
        }

        return $programados;
    }
}
