<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_operaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('usuario_nombre', 150);
            $table->string('usuario_rol', 30);
            $table->string('accion', 100);
            $table->text('detalle');
            $table->unsignedBigInteger('examen_id')->nullable();
            $table->string('examen_nombre', 200)->nullable();
            $table->unsignedInteger('registros_afectados')->default(1);
            $table->string('estado', 12)->default('correcto');
            $table->text('error')->nullable();
            $table->timestamp('fecha_hora')->useCurrent();

            $table->index(['fecha_hora', 'id'], 'historial_fecha_idx');
            $table->index(['estado', 'fecha_hora'], 'historial_estado_fecha_idx');
            $table->index(['usuario_nombre', 'fecha_hora'], 'historial_usuario_fecha_idx');
            $table->index(['examen_id', 'fecha_hora'], 'historial_examen_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_operaciones');
    }
};
