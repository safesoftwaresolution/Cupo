<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barberias', function (Blueprint $table) {
            // Algunas barberías pagan a sus barberos por comisión y otras no (sueldo fijo, silla alquilada).
            $table->boolean('maneja_comisiones')->default(true)->after('activa');
            $table->boolean('recordatorios_activos')->default(true)->after('maneja_comisiones');
            // Cuántas horas antes de la cita se avisa al cliente por WhatsApp.
            $table->unsignedSmallInteger('recordatorio_horas')->default(24)->after('recordatorios_activos');
        });
    }

    public function down(): void
    {
        Schema::table('barberias', function (Blueprint $table) {
            $table->dropColumn(['maneja_comisiones', 'recordatorios_activos', 'recordatorio_horas']);
        });
    }
};
