<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ambientes', function (Blueprint $table) {
            $table->string('ubicacion', 160)->nullable()->after('nombre');
        });

        DB::table('ambientes')
            ->where('nombre', 'Edificio Central 102A')
            ->whereNull('ubicacion')
            ->update(['ubicacion' => 'Planta baja, Bloque Central']);
    }

    public function down(): void
    {
        Schema::table('ambientes', function (Blueprint $table) {
            $table->dropColumn('ubicacion');
        });
    }
};
