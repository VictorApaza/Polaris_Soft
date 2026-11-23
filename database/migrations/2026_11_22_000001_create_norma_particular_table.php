<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normas PARTICULARES de un estudiante en un examen (requerimiento general 2 del proyecto).
     *
     * Las normas generales ya viven en `examenes` (normas_admision / normas_salida). Esta tabla guarda la norma
     * propia de un estudiante concreto en un examen (p. ej. «puede usar calculadora por indicación del docente»,
     * «tiene 30 min adicionales»). El personal de control la ve al verificar el ingreso del estudiante.
     *
     * - Una sola norma por estudiante y examen: índice único (examen_id, estudiante_id).
     * - Es independiente de `habilitacion`: se puede registrar aunque el estudiante aún esté «sin evaluar», y no se
     *   pierde al habilitar, inhabilitar o quitar la decisión.
     * - Sin claves foráneas, igual que el resto de tablas de registro (expulsion, ingreso...).
     */
    public function up(): void
    {
        if (Schema::hasTable('norma_particular')) {
            return;
        }

        Schema::create('norma_particular', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('examen_id');                // examenes.id
            $table->unsignedBigInteger('estudiante_id');            // estudiante.id_estudiante
            $table->string('norma', 1000);
            $table->unsignedBigInteger('registrado_por');           // users.id (quien la escribió o la editó por última vez)
            $table->timestamps();

            $table->unique(['examen_id', 'estudiante_id'], 'norma_particular_examen_estudiante_unique');
            $table->index('estudiante_id', 'norma_particular_estudiante_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('norma_particular');
    }
};
