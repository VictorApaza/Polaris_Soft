<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('estudiante')) {
            return;
        }

        Schema::table('estudiante', function (Blueprint $table) {
            if (! Schema::hasColumn('estudiante', 'carrera')) {
                $table->string('carrera', 120)->nullable();
            }

            if (! Schema::hasColumn('estudiante', 'correo_institucional')) {
                $table->string('correo_institucional', 150)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('estudiante')) {
            return;
        }

        Schema::table('estudiante', function (Blueprint $table) {
            $columns = [];

            foreach (['carrera', 'correo_institucional'] as $column) {
                if (Schema::hasColumn('estudiante', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};