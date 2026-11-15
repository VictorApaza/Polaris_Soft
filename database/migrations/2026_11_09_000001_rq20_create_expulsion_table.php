<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RQ20: estudiantes expulsados de un examen, con el motivo, la hora y quién lo registró.
     *
     * - Un estudiante solo puede ser expulsado una vez por examen: índice único (examen_id, estudiante_id).
     *   El Control en vivo (RQ14) ya busca esta tabla por esos dos campos para marcar "EXPULSADO".
     * - `fecha_hora` la asigna el SERVIDOR (America/La_Paz); el valor por defecto usa la hora de la sesión MySQL.
     * - `ambiente_id`: aula en la que había ingresado el estudiante (null si el aula no está en el catálogo).
     * - `registrado_por`: users.id de quien registró la expulsión.
     * - Sin claves foráneas, igual que `ingreso` y `transparencia_ingreso`: es un registro histórico y no debe
     *   borrarse ni bloquearse porque alguien elimine un examen o un estudiante.
     */
    public function up(): void
    {
        if (Schema::hasTable('expulsion')) {
            return;
        }

        Schema::create('expulsion', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('examen_id');                // examenes.id
            $table->unsignedBigInteger('estudiante_id');            // estudiante.id_estudiante
            $table->unsignedBigInteger('ambiente_id')->nullable();  // ambientes.id
            $table->string('motivo', 500);
            $table->unsignedBigInteger('registrado_por');           // users.id
            $table->timestamp('fecha_hora')->useCurrent();          // hora del servidor (America/La_Paz)

            $table->unique(['examen_id', 'estudiante_id'], 'expulsion_examen_estudiante_unique');
            $table->index(['examen_id', 'fecha_hora'], 'expulsion_examen_fecha_idx');
            $table->index('estudiante_id', 'expulsion_estudiante_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expulsion');
    }
};
