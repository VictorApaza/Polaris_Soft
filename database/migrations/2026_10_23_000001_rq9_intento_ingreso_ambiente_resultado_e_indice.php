<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RQ9 (Sprint 3): comprobar que el estudiante esté habilitado antes de permitir el ingreso.
     *
     * 1) intento_ingreso (ingresos rechazados) ya existía; se le agregan las columnas que pide el backlog:
     *    ambiente_id (aula del examen) y resultado (RECHAZADO).
     * 2) La consulta a `habilitacion` por (estudiante_id, examen_id) debe responder en menos de 1 segundo:
     *    se asegura que exista un índice compuesto en ese orden (las bases nuevas ya lo tienen por la clave única).
     */
    public function up(): void
    {
        if (Schema::hasTable('intento_ingreso')) {
            if (! Schema::hasColumn('intento_ingreso', 'ambiente_id')) {
                Schema::table('intento_ingreso', function (Blueprint $table) {
                    $table->unsignedBigInteger('ambiente_id')->nullable()->after('examen_id');
                });
            }

            if (! Schema::hasColumn('intento_ingreso', 'resultado')) {
                Schema::table('intento_ingreso', function (Blueprint $table) {
                    $table->string('resultado', 20)->default('RECHAZADO')->after('dato_verificado');
                });
            }
        }

        $this->asegurarIndiceHabilitacion();
    }

    public function down(): void
    {
        if (Schema::hasTable('intento_ingreso')) {
            foreach (['resultado', 'ambiente_id'] as $columna) {
                if (Schema::hasColumn('intento_ingreso', $columna)) {
                    Schema::table('intento_ingreso', fn (Blueprint $t) => $t->dropColumn($columna));
                }
            }
        }
        // El índice de habilitacion se deja: no estorba y acelera la consulta.
    }

    private function asegurarIndiceHabilitacion(): void
    {
        if (! Schema::hasTable('habilitacion')
            || ! Schema::hasColumn('habilitacion', 'estudiante_id')
            || ! Schema::hasColumn('habilitacion', 'examen_id')) {
            return;
        }

        try {
            $porIndice = collect(DB::select('SHOW INDEX FROM `habilitacion`'))->groupBy('Key_name');

            $yaExiste = $porIndice->contains(function ($columnas) {
                return $columnas->sortBy('Seq_in_index')->pluck('Column_name')->take(2)->values()->all() === ['estudiante_id', 'examen_id'];
            });

            if (! $yaExiste) {
                Schema::table('habilitacion', function (Blueprint $table) {
                    $table->index(['estudiante_id', 'examen_id'], 'habilitacion_estudiante_examen_idx');
                });
            }
        } catch (\Throwable $e) {
            // Si el motor no soporta SHOW INDEX, no se bloquea la migración.
        }
    }
};
