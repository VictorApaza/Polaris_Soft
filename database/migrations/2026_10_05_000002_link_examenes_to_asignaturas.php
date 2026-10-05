<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asignatura')) {
            Schema::create('asignatura', function (Blueprint $table) {
                $table->bigInteger('id_asignatura')->autoIncrement();
                $table->string('codigo', 30)->unique();
                $table->string('nombre', 150);
            });
        }

        if (! Schema::hasTable('estudiante_asignatura')) {
            Schema::create('estudiante_asignatura', function (Blueprint $table) {
                $table->bigIncrements('id_estudiante_asignatura');
                $table->bigInteger('id_estudiante');
                $table->bigInteger('id_asignatura');
                $table->string('gestion', 20);
                $table->string('estado', 20)->default('ACTIVO');
            });
        }

        if (! Schema::hasColumn('examenes', 'asignatura_id')) {
            Schema::table('examenes', function (Blueprint $table) {
                $table->bigInteger('asignatura_id')->nullable();
                $table->foreign('asignatura_id')
                    ->references('id_asignatura')
                    ->on('asignatura')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasColumn('examenes', 'materia_id') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE examenes MODIFY materia_id BIGINT UNSIGNED NULL');
        } elseif (Schema::hasColumn('examenes', 'materia_id')) {
            Schema::table('examenes', function (Blueprint $table) {
                $table->unsignedBigInteger('materia_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('examenes', 'asignatura_id')) {
            Schema::table('examenes', function (Blueprint $table) {
                $table->dropForeign(['asignatura_id']);
                $table->dropColumn('asignatura_id');
            });
        }

        if (Schema::hasColumn('examenes', 'materia_id') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE examenes MODIFY materia_id BIGINT UNSIGNED NOT NULL');
        } elseif (Schema::hasColumn('examenes', 'materia_id')) {
            Schema::table('examenes', function (Blueprint $table) {
                $table->unsignedBigInteger('materia_id')->nullable(false)->change();
            });
        }
    }
};
