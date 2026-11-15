<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RQ21: situaciones excepcionales durante un examen (problemas de identificación, cambios de ambiente,
     * estudiantes no habilitados que reclaman, etc.), con su detalle, quién las registró y si ya fueron revisadas.
     *
     * - `categoria`: PROBLEMA_IDENTIFICACION | CAMBIO_AMBIENTE | ESTUDIANTE_NO_HABILITADO | OTRA
     *   (se valida en la aplicación: App\Models\SituacionExcepcional::CATEGORIAS).
     * - `estudiante_id`: puede ser null (una situación general, o alguien a quien no se pudo identificar);
     *   la aplicación lo exige en CAMBIO_AMBIENTE y ESTUDIANTE_NO_HABILITADO.
     * - `ambiente_origen_id` / `ambiente_destino_id`: solo se llenan en CAMBIO_AMBIENTE (de qué aula a cuál).
     * - `registrado_por`: users.id de quien la registró (el usuario autenticado, nunca lo envía el navegador).
     * - `fecha_hora`: la asigna el SERVIDOR (America/La_Paz); el valor por defecto usa la hora de la sesión MySQL.
     * - `estado`: PENDIENTE al registrarla; REVISADA cuando alguien la marca (con `revisada_por` y `revisada_at`,
     *   igual que `incidencia`).
     * - Sin claves foráneas, igual que `ingreso`, `expulsion` y `transparencia_ingreso`: es un registro histórico y
     *   no debe borrarse ni bloquearse porque alguien elimine un examen, un estudiante o un ambiente.
     */
    public function up(): void
    {
        if (Schema::hasTable('situacion_excepcional')) {
            return;
        }

        Schema::create('situacion_excepcional', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('examen_id');                       // examenes.id
            $table->unsignedBigInteger('estudiante_id')->nullable();       // estudiante.id_estudiante
            $table->string('categoria', 40);
            $table->string('detalle', 1000);
            $table->unsignedBigInteger('ambiente_origen_id')->nullable();  // ambientes.id (solo CAMBIO_AMBIENTE)
            $table->unsignedBigInteger('ambiente_destino_id')->nullable(); // ambientes.id (solo CAMBIO_AMBIENTE)
            $table->unsignedBigInteger('registrado_por');                  // users.id
            $table->timestamp('fecha_hora')->useCurrent();                 // hora del servidor (America/La_Paz)
            $table->string('estado', 20)->default('PENDIENTE');            // PENDIENTE | REVISADA

            $table->unsignedBigInteger('revisada_por')->nullable();        // users.id
            $table->timestamp('revisada_at')->nullable();

            // Pestaña "Situaciones" de Control en vivo: por examen, de la más reciente a la más antigua y por estado.
            $table->index(['examen_id', 'fecha_hora'], 'situacion_examen_fecha_idx');
            $table->index(['examen_id', 'estado'], 'situacion_examen_estado_idx');
            $table->index('estudiante_id', 'situacion_estudiante_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('situacion_excepcional');
    }
};
