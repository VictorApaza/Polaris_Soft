<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `ingreso`: registro oficial de la fecha y hora en que el personal de control AUTORIZA el ingreso
     * de un estudiante a un examen.
     *
     * - Vínculos: estudiante, examen, ambiente (aula) y controlador que autorizó.
     * - `fecha_hora_ingreso`: la asigna el SERVIDOR (nunca el navegador) en la zona America/La_Paz.
     *   Aquí el valor por defecto usa la hora de la sesión MySQL (config/database.php → 'timezone' = -04:00),
     *   y la aplicación la fija explícitamente con la zona de config/app.php (America/La_Paz).
     * - Un estudiante solo puede tener UN ingreso por examen (RQ11): índice único (estudiante_id, examen_id).
     * - Es de solo escritura: el modelo App\Models\Ingreso bloquea update/delete y, en MySQL, triggers rechazan
     *   cualquier UPDATE o DELETE hecho directamente en la base.
     * - Sin claves foráneas, igual que `transparencia_ingreso`: es una bitácora y no debe borrarse ni bloquearse
     *   porque alguien elimine un examen o un estudiante.
     *
     * Se guarda en la misma transacción que `transparencia_ingreso` (App\Services\IngresoService).
     */
    public function up(): void
    {
        if (Schema::hasTable('ingreso')) {
            return;
        }

        Schema::create('ingreso', function (Blueprint $table) {
            $table->id();

            // --- Vínculos ---
            $table->unsignedBigInteger('estudiante_id');            // estudiante.id_estudiante
            $table->unsignedBigInteger('examen_id');                // examenes.id
            $table->unsignedBigInteger('ambiente_id')->nullable();  // ambientes.id (null si el nombre del aula no está en el catálogo)
            $table->unsignedBigInteger('controlador_id');           // users.id (quien autorizó)

            // --- Datos del ingreso ---
            $table->string('metodo_verificacion', 20);              // CODIGO_UNIV | CI | QR
            $table->timestamp('fecha_hora_ingreso')->useCurrent();  // hora del servidor (America/La_Paz)

            // --- Índices: consultas por examen (con orden cronológico) y por estudiante ---
            $table->index(['examen_id', 'fecha_hora_ingreso'], 'ingreso_examen_fecha_idx');
            $table->index('estudiante_id', 'ingreso_estudiante_idx');
            $table->unique(['estudiante_id', 'examen_id'], 'ingreso_estudiante_examen_unique');
        });

        $this->crearTriggersDeInmutabilidad();
    }

    public function down(): void
    {
        // Al eliminar la tabla también se eliminan sus triggers.
        Schema::dropIfExists('ingreso');
    }

    private function crearTriggersDeInmutabilidad(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::unprepared(
                "CREATE TRIGGER ingreso_no_update BEFORE UPDATE ON ingreso "
                ."FOR EACH ROW SIGNAL SQLSTATE '45000' "
                ."SET MESSAGE_TEXT = 'ingreso es inmutable: no se permite modificar registros.'"
            );
            DB::unprepared(
                "CREATE TRIGGER ingreso_no_delete BEFORE DELETE ON ingreso "
                ."FOR EACH ROW SIGNAL SQLSTATE '45000' "
                ."SET MESSAGE_TEXT = 'ingreso es inmutable: no se permite eliminar registros.'"
            );
        } catch (\Throwable $e) {
            // Si el usuario de la BD no tiene el privilegio TRIGGER, la migración continúa:
            // la inalterabilidad queda garantizada por el modelo (App\Models\Ingreso).
        }
    }
};
