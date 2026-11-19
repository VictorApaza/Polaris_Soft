<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** RQ24: el historial guarda también la IP desde donde se hizo la operación y tiene índice por acción. */
    public function up(): void
    {
        if (! Schema::hasColumn('historial_operaciones', 'ip')) {
            Schema::table('historial_operaciones', function (Blueprint $t) {
                $t->string('ip', 45)->nullable()->after('usuario_rol');
            });
        }

        try {
            Schema::table('historial_operaciones', function (Blueprint $t) {
                $t->index(['accion', 'fecha_hora'], 'historial_accion_fecha_idx');
            });
        } catch (\Throwable $e) {
            // el índice ya existe: no se hace nada
        }
    }

    public function down(): void
    {
        // Se dejan la columna y el índice: el historial no se modifica.
    }
};
