<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Para detectar la misma foto en estudiantes distintos:
     *  - foto_hash:   SHA-256 del archivo (copia exacta).
     *  - foto_huella: huella perceptual de 64 bits en hexadecimal (misma foto reescalada, recomprimida o con otro nombre).
     * Las fotos que ya estaban cargadas se indexan con:  php artisan fotos:indexar
     */
    public function up(): void
    {
        if (! Schema::hasColumn('estudiante', 'foto_hash')) {
            Schema::table('estudiante', function (Blueprint $t) {
                $t->string('foto_hash', 64)->nullable()->index();
            });
        }
        if (! Schema::hasColumn('estudiante', 'foto_huella')) {
            Schema::table('estudiante', function (Blueprint $t) {
                $t->string('foto_huella', 16)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['foto_hash', 'foto_huella'] as $columna) {
            if (Schema::hasColumn('estudiante', $columna)) {
                Schema::table('estudiante', fn (Blueprint $t) => $t->dropColumn($columna));
            }
        }
    }
};
