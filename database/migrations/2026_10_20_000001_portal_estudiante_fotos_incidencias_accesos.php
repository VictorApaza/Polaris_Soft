<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - estudiante: foto actual y su revisión por el docente.
     * - incidencia: intentos duplicados de ingreso, suplantación, foto que no coincide, etc.
     * - acceso_log: cada intento de ingreso al sistema (correcto o fallido) para la transparencia.
     * - motivos de inhabilitación nuevos: trampa y foto no válida.
     */
    public function up(): void
    {
        // ---- Foto del estudiante ----
        $columnas = [
            'foto_path' => fn (Blueprint $t) => $t->string('foto_path', 255)->nullable(),
            'foto_estado' => fn (Blueprint $t) => $t->string('foto_estado', 20)->default('SIN_FOTO'),   // SIN_FOTO | PENDIENTE | APROBADA | OBSERVADA
            'foto_observacion' => fn (Blueprint $t) => $t->string('foto_observacion', 300)->nullable(),
            'foto_subida_at' => fn (Blueprint $t) => $t->timestamp('foto_subida_at')->nullable(),
            'foto_revisada_at' => fn (Blueprint $t) => $t->timestamp('foto_revisada_at')->nullable(),
            'foto_revisada_por' => fn (Blueprint $t) => $t->unsignedBigInteger('foto_revisada_por')->nullable(),
        ];

        foreach ($columnas as $nombre => $definir) {
            if (! Schema::hasColumn('estudiante', $nombre)) {
                Schema::table('estudiante', fn (Blueprint $t) => $definir($t));
            }
        }

        // ---- Incidencias ----
        if (! Schema::hasTable('incidencia')) {
            Schema::create('incidencia', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('examen_id');
                $table->unsignedBigInteger('estudiante_id')->nullable();
                $table->string('tipo', 40);              // INTENTO_DUPLICADO | SUPLANTACION_SOSPECHADA | FOTO_NO_COINCIDE | TRAMPA | OTRO
                $table->string('metodo', 30)->nullable(); // CODIGO_UNIVERSITARIO | DOCUMENTO_IDENTIDAD | CODIGO_QR
                $table->text('detalle')->nullable();
                $table->unsignedBigInteger('controlador_id')->nullable();   // users.id de quien la registró
                $table->timestamp('ingreso_original_at')->nullable();       // cuándo ingresó la primera vez (duplicados)
                $table->timestamp('fecha_hora')->useCurrent();              // cuándo ocurrió (hora del servidor)
                $table->string('estado', 15)->default('ABIERTA');          // ABIERTA | REVISADA
                $table->unsignedBigInteger('revisada_por')->nullable();
                $table->timestamp('revisada_at')->nullable();

                $table->index(['examen_id', 'fecha_hora'], 'incid_examen_fecha_idx');
                $table->index('estudiante_id', 'incid_estudiante_idx');
            });
        }

        // ---- Bitácora de accesos al sistema ----
        if (! Schema::hasTable('acceso_log')) {
            Schema::create('acceso_log', function (Blueprint $table) {
                $table->id();
                $table->string('tipo', 12);                 // PERSONAL | ESTUDIANTE
                $table->string('identificador', 150);       // correo o código universitario que se intentó usar (nunca la contraseña)
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->boolean('exitoso');
                $table->string('resultado', 30);            // OK | NO_EXISTE | INACTIVO | CONTRASENA_INCORRECTA | BLOQUEADO
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('fecha_hora')->useCurrent();

                $table->index('fecha_hora', 'acceso_fecha_idx');
                $table->index(['exitoso', 'fecha_hora'], 'acceso_exito_fecha_idx');
            });
        }

        // ---- Motivos de inhabilitación nuevos (no duplica si ya existen) ----
        $ahora = now();
        foreach (['Trampa o suplantación detectada', 'No subió una foto válida'] as $descripcion) {
            DB::table('motivo_inhabilitacion')->insertOrIgnore([
                'descripcion' => $descripcion,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('acceso_log');
        Schema::dropIfExists('incidencia');

        foreach (['foto_revisada_por', 'foto_revisada_at', 'foto_subida_at', 'foto_observacion', 'foto_estado', 'foto_path'] as $c) {
            if (Schema::hasColumn('estudiante', $c)) {
                Schema::table('estudiante', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
