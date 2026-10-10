<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Catálogo de causas predefinidas de inhabilitación (RQ4). Tabla: motivo_inhabilitacion. */
    public function up(): void
    {
        Schema::create('motivo_inhabilitacion', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 150)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motivo_inhabilitacion');
    }
};
