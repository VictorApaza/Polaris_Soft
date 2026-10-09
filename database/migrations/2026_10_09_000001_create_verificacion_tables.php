<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('habilitacion')) {
            Schema::create('habilitacion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_estudiante');
                $table->unsignedBigInteger('id_examen');
                $table->boolean('habilitado')->default(true);
                $table->string('motivo_inhabilitacion', 255)->nullable();
                $table->string('ambiente', 100)->nullable();
                $table->timestamp('fecha_hora')->useCurrent();
                $table->timestamps();

                $table->unique(['id_estudiante', 'id_examen']);
            });
        }

        if (! Schema::hasTable('verificacion')) {
            Schema::create('verificacion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_estudiante')->nullable();
                $table->unsignedBigInteger('id_examen');
                $table->string('tipo', 50)->default('CODIGO_UNIVERSITARIO');
                $table->string('dato_verificado', 100);
                $table->string('resultado', 50);
                $table->string('ambiente', 100)->nullable();
                $table->timestamp('fecha_hora')->useCurrent();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('intento_ingreso')) {
            Schema::create('intento_ingreso', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_estudiante')->nullable();
                $table->unsignedBigInteger('id_examen');
                $table->string('tipo', 50)->default('CODIGO_UNIVERSITARIO');
                $table->string('dato_verificado', 100)->nullable();
                $table->text('motivo');
                $table->string('ambiente', 100)->nullable();
                $table->timestamp('fecha_hora')->useCurrent();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('intento_ingreso');
        Schema::dropIfExists('verificacion');
        Schema::dropIfExists('habilitacion');
    }
};
