<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->unsignedSmallInteger('capacidad');
            $table->timestamps();
        });

        $registrados = [];
        foreach (DB::table('examenes')->orderByDesc('id')->get(['ambiente', 'capacidad']) as $examen) {
            $nombre = trim((string) $examen->ambiente);
            $clave = mb_strtolower($nombre);

            if ($nombre === '' || isset($registrados[$clave])) {
                continue;
            }

            $registrados[$clave] = true;
            DB::table('ambientes')->insert([
                'nombre' => $nombre,
                'capacidad' => max(1, min((int) $examen->capacidad, 5000)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ambientes');
    }
};
