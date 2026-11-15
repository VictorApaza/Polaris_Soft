<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RQ14 / RQ15 (Control en vivo): índices para que las listas de "Ingresaron" y "Pendientes" respondan en
     * menos de 1 segundo con 2.000 estudiantes, aunque se consulten cada 5 segundos.
     *
     *  - ingreso (examen_id, fecha_hora_ingreso): ya lo crea la migración de `ingreso`; aquí solo se asegura.
     *  - habilitacion (examen_id, estado): para sacar rápido los habilitados de un examen (pendientes).
     * No se borra ni se cambia ningún dato.
     */
    public function up(): void
    {
        $this->asegurarIndice('ingreso', ['examen_id', 'fecha_hora_ingreso'], 'ingreso_examen_fecha_idx');
        $this->asegurarIndice('habilitacion', ['examen_id', 'estado'], 'habilitacion_examen_estado_idx');
    }

    public function down(): void
    {
        // Los índices se dejan: no estorban y aceleran las consultas.
    }

    private function asegurarIndice(string $tabla, array $columnas, string $nombre): void
    {
        if (! Schema::hasTable($tabla)) {
            return;
        }
        foreach ($columnas as $c) {
            if (! Schema::hasColumn($tabla, $c)) {
                return;
            }
        }

        try {
            $porIndice = collect(DB::select("SHOW INDEX FROM `{$tabla}`"))->groupBy('Key_name');
            $yaExiste = $porIndice->contains(
                fn ($cols) => $cols->sortBy('Seq_in_index')->pluck('Column_name')->take(count($columnas))->values()->all() === $columnas
            );
            if (! $yaExiste) {
                Schema::table($tabla, fn (Blueprint $t) => $t->index($columnas, $nombre));
            }
        } catch (\Throwable $e) {
            // Si el motor no soporta SHOW INDEX, no se bloquea la migración.
        }
    }
};
