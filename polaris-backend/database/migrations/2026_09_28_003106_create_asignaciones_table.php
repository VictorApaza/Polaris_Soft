<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('estudiante_id');

            $table->foreignId('materia_id')
                ->constrained('materias')
                ->cascadeOnDelete();

            $table->foreignId('grupo_id')
                ->constrained('grupos')
                ->cascadeOnDelete();

            $table->foreignId('docente_id')
                ->constrained('docentes')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique([
                'estudiante_id',
                'materia_id',
                'grupo_id'
            ]);

            $table->foreign('estudiante_id')
                ->references('id_estudiante')
                ->on('estudiante')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones');
    }
};