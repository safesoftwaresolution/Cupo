<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cierres_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barberia_id')->constrained('barberias')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha');
            $table->unsignedInteger('efectivo_esperado')->default(0);
            $table->unsignedInteger('efectivo_contado')->default(0);
            $table->integer('diferencia')->default(0); // contado - esperado, puede ser negativa
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['barberia_id', 'fecha']);
        });

        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barberia_id')->constrained('barberias')->cascadeOnDelete();
            $table->foreignId('cita_id')->nullable()->unique()->constrained('citas')->nullOnDelete();
            $table->foreignId('barbero_id')->constrained('barberos')->restrictOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cierre_caja_id')->nullable()->constrained('cierres_caja')->nullOnDelete();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('descuento')->default(0);
            $table->unsignedInteger('propina')->default(0);
            $table->unsignedInteger('total');
            $table->string('metodo_pago', 20);
            // Foto de la comisión vigente al cobrar: cambiarla después no altera ventas pasadas.
            $table->decimal('comision_porcentaje', 5, 2);
            $table->unsignedInteger('comision_monto');
            $table->timestamp('pagada_en');
            $table->timestamps();

            $table->index(['barberia_id', 'pagada_en']);
            $table->index(['barbero_id', 'pagada_en']);
        });

        Schema::create('venta_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('servicio_id')->nullable()->constrained('servicios')->nullOnDelete();
            $table->string('descripcion');
            $table->unsignedSmallInteger('cantidad')->default(1);
            $table->unsignedInteger('precio_unitario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_items');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('cierres_caja');
    }
};
