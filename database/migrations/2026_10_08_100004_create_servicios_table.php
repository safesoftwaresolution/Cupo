<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barberia_id')->constrained('barberias')->cascadeOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('duracion_minutos');
            $table->unsignedInteger('precio'); // pesos colombianos, sin decimales
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['barberia_id', 'activo']);
        });

        // Qué servicios hace cada barbero, con precio propio opcional.
        Schema::create('barbero_servicio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbero_id')->constrained('barberos')->cascadeOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->unsignedInteger('precio_especial')->nullable();

            $table->unique(['barbero_id', 'servicio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barbero_servicio');
        Schema::dropIfExists('servicios');
    }
};
