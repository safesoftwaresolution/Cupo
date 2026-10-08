<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barberia_id')->constrained('barberias')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('telefono', 20); // solo dígitos con indicativo, ej. 573001234567
            $table->string('email')->nullable();
            $table->text('notas')->nullable();
            // Autorización de tratamiento de datos / mensajes (Ley 1581 de 2012).
            $table->boolean('acepta_whatsapp')->default(false);
            $table->timestamp('acepto_en')->nullable();
            $table->timestamps();

            $table->unique(['barberia_id', 'telefono']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
