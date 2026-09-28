<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->string('email')->unique();

            $table->timestamp('email_verified_at')->nullable();

            $table->string('password');

            $table->boolean('activo')->default(true);

            // Rol del usuario
            $table->foreignId('rol_id')
                ->constrained('roles')
                ->restrictOnDelete();

            // Relación opcional con estudiante
            $table->unsignedBigInteger('estudiante_id')
                ->nullable();

            // Relación opcional con docente
            $table->unsignedBigInteger('docente_id')
                ->nullable();

            $table->rememberToken();

            $table->timestamps();

            // FK estudiante
            $table->foreign('estudiante_id')
                ->references('id_estudiante')
                ->on('estudiante')
                ->nullOnDelete();

            // FK docente
            $table->foreign('docente_id')
                ->references('id')
                ->on('docentes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};