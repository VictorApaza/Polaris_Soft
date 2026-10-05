
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
                ['materia_id', 'fecha', 'hora_inicio', 'ambiente'],
                'examenes_materia_fecha_hora_ambiente_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('examenes', function (Blueprint $table) {
            $table->dropUnique(
                'examenes_materia_fecha_hora_ambiente_unique'
            );
        });
    }
};

