<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barberias', function (Blueprint $table) {
            // Minutos mínimos entre "ahora" y la hora de una cita reservada por el cliente.
            $table->unsignedSmallInteger('anticipacion_minima')->default(60)->after('intervalo_agenda');
            // Cuántos días hacia adelante puede reservar un cliente.
            $table->unsignedSmallInteger('dias_reserva')->default(30)->after('anticipacion_minima');
        });
    }

    public function down(): void
    {
        Schema::table('barberias', function (Blueprint $table) {
            $table->dropColumn(['anticipacion_minima', 'dias_reserva']);
        });
    }
};
