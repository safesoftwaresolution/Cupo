<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barberia_id')->constrained('barberias')->cascadeOnDelete();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('tipo', 30); // 'recordatorio'
            $table->string('telefono', 20);
            $table->string('plantilla')->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->string('wa_message_id')->nullable()->index();
            $table->text('error')->nullable();
            $table->timestamp('enviado_en')->nullable();
            // Respuesta del cliente a los botones del mensaje ('confirmo' o 'cancelo').
            $table->string('respuesta', 20)->nullable();
            $table->timestamp('respondido_en')->nullable();
            $table->timestamps();

            // Una cita recibe a lo sumo un mensaje de cada tipo, aunque el proceso corra varias veces.
            $table->unique(['cita_id', 'tipo']);
            $table->index(['barberia_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes_whatsapp');
    }
};
