<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barberos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barberia_id')->constrained('barberias')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('nombre');
            $table->string('telefono', 20)->nullable();
            $table->decimal('comision_porcentaje', 5, 2)->default(50);
            $table->string('color', 7)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['barberia_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barberos');
    }
};
