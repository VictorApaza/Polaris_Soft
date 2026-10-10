<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `transparencia_ingreso` (RQ8 / HU-011): instantánea inmutable que se guarda cada vez
     * que el personal de control AUTORIZA el ingreso de un estudiante a un examen.
     *
     * - Vínculos: estudiante, examen, ambiente (aula/laboratorio) y controlador que autorizó.
     * - Hora: la pone el servidor (nunca el navegador).
     * - Método de verificación: CODIGO_UNIV | CI | QR.
     * - Columnas "instantánea" (*_nombre, *_codigo, examen_descripcion): guardan el texto tal como estaba
     *   al autorizar, para que la bitácora siga siendo legible aunque luego se edite o elimine el original.
     * - Metadatos de acceso: IP y navegador desde donde se autorizó.
     *
     * Sin claves foráneas a propósito: es una bitácora de auditoría y no debe borrarse ni quedar bloqueada
     * porque alguien elimine un examen o un estudiante (igual que `habilitacion`, que tampoco enlaza al estudiante).
     *
     * Inalterabilidad: además del bloqueo en el modelo Eloquent, en MySQL/MariaDB se crean triggers que
     * rechazan cualquier UPDATE o DELETE, incluso hechos a mano desde la consola SQL.
     */
    public function up(): void
    {
        Schema::create('transparencia_ingreso', function (Blueprint $table) {
            $table->id();

            // --- Vínculos ---
            $table->unsignedBigInteger('estudiante_id');            // estudiante.id_estudiante
            $table->unsignedBigInteger('examen_id');                // examenes.id
            $table->unsignedBigInteger('ambiente_id')->nullable();  // ambientes.id (null si el nombre no está en el catálogo)
            $table->unsignedBigInteger('controlador_id');           // users.id (quien autorizó)

            // --- Datos de la autorización ---
            $table->string('metodo_verificacion', 20);              // CODIGO_UNIV | CI | QR
            $table->timestamp('fecha_hora_ingreso')->useCurrent();  // hora del servidor

            // --- Instantánea legible (copia del texto al momento de autorizar) ---
            $table->string('estudiante_codigo', 20);
            $table->string('estudiante_nombre', 200);
            $table->string('examen_descripcion', 255);
            $table->string('ambiente_nombre', 100);
            $table->string('controlador_nombre', 150);

            // --- Metadatos de acceso ---
            $table->string('ip_origen', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            // --- Índices para las consultas de auditoría (por examen, estudiante, aula, controlador y fecha) ---
            $table->index(['examen_id', 'fecha_hora_ingreso'], 'transp_examen_fecha_idx');
            $table->index('estudiante_id', 'transp_estudiante_idx');
            $table->index('ambiente_nombre', 'transp_ambiente_idx');
            $table->index('controlador_id', 'transp_controlador_idx');
            $table->index('fecha_hora_ingreso', 'transp_fecha_idx');
        });

        $this->crearTriggersDeInmutabilidad();
    }

    public function down(): void
    {
        // Al eliminar la tabla también se eliminan sus triggers.
        Schema::dropIfExists('transparencia_ingreso');
    }

    private function crearTriggersDeInmutabilidad(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::unprepared(
                "CREATE TRIGGER transparencia_ingreso_no_update BEFORE UPDATE ON transparencia_ingreso "
                ."FOR EACH ROW SIGNAL SQLSTATE '45000' "
                ."SET MESSAGE_TEXT = 'transparencia_ingreso es inmutable: no se permite modificar registros.'"
            );
            DB::unprepared(
                "CREATE TRIGGER transparencia_ingreso_no_delete BEFORE DELETE ON transparencia_ingreso "
                ."FOR EACH ROW SIGNAL SQLSTATE '45000' "
                ."SET MESSAGE_TEXT = 'transparencia_ingreso es inmutable: no se permite eliminar registros.'"
            );
        } catch (\Throwable $e) {
            // Si el usuario de la BD no tiene el privilegio TRIGGER, la migración continúa:
            // la inalterabilidad queda garantizada por el modelo (App\Models\TransparenciaIngreso).
        }
    }
};
