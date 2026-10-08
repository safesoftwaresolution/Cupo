<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Franjas de trabajo semanales. Varias filas por día permiten turnos partidos (almuerzo).
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barberia_id')->constrained('barberias')->cascadeOnDelete();
            $table->foreignId('barbero_id')->constrained('barberos')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // ISO: 1 = lunes ... 7 = domingo
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();

            $table->index(['barbero_id', 'dia_semana']);
        });

        // Ausencias puntuales: vacaciones, citas médicas, festivos.
        Schema::create('bloqueos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barberia_id')->constrained('barberias')->cascadeOnDelete();
            $table->foreignId('barbero_id')->constrained('barberos')->cascadeOnDelete();
            $table->dateTime('inicia_en');
            $table->dateTime('termina_en');
            $table->string('motivo')->nullable();
            $table->timestamps();

            $table->index(['barbero_id', 'inicia_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloqueos');
        Schema::dropIfExists('horarios');
    }
};
