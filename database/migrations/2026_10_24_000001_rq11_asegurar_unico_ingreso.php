<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RQ11 (Sprint 3): un estudiante solo puede registrar su ingreso UNA vez por examen.
     *
     * La migración que crea `ingreso` ya incluye la clave única (estudiante_id, examen_id). Esta migración solo
     * se asegura de que exista también en bases donde la tabla se creó antes (no hace nada si ya está).
     * Si hubiera ingresos duplicados viejos, no se crea y el sistema sigue protegido por el chequeo del controlador.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ingreso')
            || ! Schema::hasColumn('ingreso', 'estudiante_id')
            || ! Schema::hasColumn('ingreso', 'examen_id')) {
            return;
        }

        try {
            $porIndice = collect(DB::select('SHOW INDEX FROM `ingreso`'))->groupBy('Key_name');

            $yaExiste = $porIndice->contains(function ($columnas) {
                return (int) $columnas->first()->Non_unique === 0
                    && $columnas->sortBy('Seq_in_index')->pluck('Column_name')->values()->all() === ['estudiante_id', 'examen_id'];
            });

            if (! $yaExiste) {
                Schema::table('ingreso', function (Blueprint $table) {
                    $table->unique(['estudiante_id', 'examen_id'], 'ingreso_estudiante_examen_unique');
                });
            }
        } catch (\Throwable $e) {
            // Duplicados previos o motor sin SHOW INDEX: se omite.
        }
    }

    public function down(): void
    {
        // La clave única es parte de la tabla `ingreso`: no se quita aquí.
    }
};
