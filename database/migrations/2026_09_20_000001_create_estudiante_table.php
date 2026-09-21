<?php
// SOLUCIÓN 1: esta migración faltaba por completo — el modelo Estudiante
// apuntaba a una tabla ("estudiante") que nunca se había creado.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudiante', function (Blueprint $table) {
            $table->id('id_estudiante');
            $table->string('codigo_universitario')->unique();
            $table->string('documento_identidad')->unique();
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('carrera')->nullable();
            $table->string('correo_institucional')->unique();
            $table->string('estado')->default('ACTIVO'); // ACTIVO | OBSERVADO | INACTIVO
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiante');
    }
};
