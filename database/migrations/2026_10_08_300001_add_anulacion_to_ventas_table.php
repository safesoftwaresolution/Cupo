<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Una venta nunca se borra: se anula y deja de contar, para conservar el rastro.
            $table->timestamp('anulada_en')->nullable()->after('pagada_en');
            $table->string('motivo_anulacion')->nullable()->after('anulada_en');
            $table->foreignId('anulada_por')->nullable()->after('motivo_anulacion')->constrained('users')->nullOnDelete();

            // Antes una cita solo podía tener UNA venta en toda su vida; ahora puede cobrarse de nuevo tras anular.
            $table->dropUnique(['cita_id']);
        });

        // Solo puede haber una venta VIGENTE por cita. SQLite y PostgreSQL (el de producción) permiten índices parciales.
        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement('CREATE UNIQUE INDEX ventas_cita_vigente_unique ON ventas (cita_id) WHERE anulada_en IS NULL AND cita_id IS NOT NULL');
        } else {
            Schema::table('ventas', fn (Blueprint $table) => $table->index('cita_id'));
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS ventas_cita_vigente_unique');
        }

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulada_por');
            $table->dropColumn(['anulada_en', 'motivo_anulacion']);
        });
    }
};
