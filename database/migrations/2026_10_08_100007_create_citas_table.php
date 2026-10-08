<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barberia_id')->constrained('barberias')->cascadeOnDelete();
            $table->foreignId('barbero_id')->constrained('barberos')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->dateTime('inicia_en');
            $table->dateTime('termina_en');
            $table->string('estado', 20)->default('pendiente');
            $table->string('origen', 20)->default('manual');
            $table->unsignedInteger('precio_total')->default(0);
            $table->unsignedSmallInteger('duracion_total_minutos')->default(0);
            $table->text('notas')->nullable();
            $table->timestamp('confirmada_en')->nullable();
            $table->timestamp('cancelada_en')->nullable();
            $table->string('motivo_cancelacion')->nullable();
            $table->timestamp('recordatorio_enviado_en')->nullable();
            $table->timestamps();

            $table->index(['barberia_id', 'inicia_en']);
            $table->index(['barbero_id', 'inicia_en']);
            $table->index(['cliente_id']);
        });

        // Servicios de la cita con foto del nombre, duración y precio al momento de reservar.
        Schema::create('cita_servicio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->constrained('citas')->cascadeOnDelete();
            $table->foreignId('servicio_id')->nullable()->constrained('servicios')->nullOnDelete();
            $table->string('nombre');
            $table->unsignedSmallInteger('duracion_minutos');
            $table->unsignedInteger('precio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cita_servicio');
        Schema::dropIfExists('citas');
    }
};
