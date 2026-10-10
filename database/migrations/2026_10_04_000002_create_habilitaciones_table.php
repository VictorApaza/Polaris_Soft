<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `habilitacion` (RQ3): qué estudiantes pueden rendir un examen puntual.
     * Si un estudiante no tiene fila aquí para un examen, está "sin evaluar".
     *
     * Índices:
     *  - único (estudiante_id, examen_id): evita duplicados y acelera "¿este estudiante está habilitado en este examen?".
     *  - (examen_id, estado): acelera "listar los habilitados / inhabilitados de un examen".
     */
    public function up(): void
    {
        Schema::create('habilitacion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('estudiante_id'); // referencia a estudiante.id_estudiante
            $table->foreignId('examen_id')->constrained('examenes')->cascadeOnDelete();
            $table->string('estado', 20)->default('habilitado'); // habilitado | inhabilitado
            $table->foreignId('motivo_inhabilitacion_id')->nullable()
                ->constrained('motivo_inhabilitacion')->nullOnDelete();
            $table->text('observacion')->nullable(); // causa en texto libre (RQ4)
            $table->timestamps();

            $table->unique(['estudiante_id', 'examen_id']);
            $table->index(['examen_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habilitacion');
    }
};
