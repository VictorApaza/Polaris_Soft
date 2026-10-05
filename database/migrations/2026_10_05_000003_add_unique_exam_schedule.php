<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examenes', function (Blueprint $table) {
            $table->unique(
                ['asignatura_id', 'carrera', 'fecha', 'hora_inicio'],
                'examenes_asignatura_carrera_fecha_hora_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('examenes', function (Blueprint $table) {
            $table->dropUnique('examenes_asignatura_carrera_fecha_hora_unique');
        });
    }
};
