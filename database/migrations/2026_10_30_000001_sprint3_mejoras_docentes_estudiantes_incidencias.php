<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - docentes: complemento del CI y vínculo con su cuenta de usuario (para ver solo sus materias).
     * - estudiante: contraseña propia (null = todavía entra con su cédula) para poder cambiarla.
     * - incidencia: evidencia (foto/prueba) y datos del suplantador (nombre, CI y foto).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('docentes', 'ci_complemento')) {
            Schema::table('docentes', function (Blueprint $t) {
                $t->string('ci_complemento', 2)->nullable()->after('ci');
            });
        }
        if (! Schema::hasColumn('docentes', 'user_id')) {
            Schema::table('docentes', function (Blueprint $t) {
                $t->unsignedBigInteger('user_id')->nullable()->after('ci_complemento');
            });
        }

        if (! Schema::hasColumn('estudiante', 'password')) {
            Schema::table('estudiante', function (Blueprint $t) {
                $t->string('password', 255)->nullable();
                $t->timestamp('password_cambiada_at')->nullable();
            });
        }

        foreach ([
            'evidencia_path' => fn (Blueprint $t) => $t->string('evidencia_path', 255)->nullable(),
            'suplantador_nombre' => fn (Blueprint $t) => $t->string('suplantador_nombre', 150)->nullable(),
            'suplantador_ci' => fn (Blueprint $t) => $t->string('suplantador_ci', 20)->nullable(),
            'suplantador_foto_path' => fn (Blueprint $t) => $t->string('suplantador_foto_path', 255)->nullable(),
        ] as $columna => $definir) {
            if (! Schema::hasColumn('incidencia', $columna)) {
                Schema::table('incidencia', fn (Blueprint $t) => $definir($t));
            }
        }
    }

    public function down(): void
    {
        foreach (['evidencia_path', 'suplantador_nombre', 'suplantador_ci', 'suplantador_foto_path'] as $c) {
            if (Schema::hasColumn('incidencia', $c)) {
                Schema::table('incidencia', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
        if (Schema::hasColumn('estudiante', 'password')) {
            Schema::table('estudiante', fn (Blueprint $t) => $t->dropColumn(['password', 'password_cambiada_at']));
        }
        foreach (['user_id', 'ci_complemento'] as $c) {
            if (Schema::hasColumn('docentes', $c)) {
                Schema::table('docentes', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
