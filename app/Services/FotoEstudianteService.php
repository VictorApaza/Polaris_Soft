<?php

namespace App\Services;

use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\MotivoInhabilitacion;

class FotoEstudianteService
{
    /**
     * Inhabilita, en ese examen, a los estudiantes habilitados que no tienen una foto válida
     * (no la subieron, o el docente la observó y no la corrigieron). Devuelve cuántos fueron.
     */
    public function inhabilitarSinFoto(Examen $examen): int
    {
        $motivo = MotivoInhabilitacion::firstOrCreate(['descripcion' => 'No subió una foto válida']);

        $habilitados = Habilitacion::where('examen_id', $examen->id)
            ->where('estado', 'habilitado')
            ->pluck('estudiante_id');

        $sinFotoValida = Estudiante::whereIn('id_estudiante', $habilitados)
            ->where(function ($q) {
                $q->whereNull('foto_path')->orWhere('foto_path', '')->orWhere('foto_estado', 'OBSERVADA');
            })
            ->get();

        foreach ($sinFotoValida as $estudiante) {
            $observacion = $estudiante->foto_estado === 'OBSERVADA'
                ? 'Foto observada y no corregida: '.$estudiante->foto_observacion
                : 'No subió su foto dentro del plazo.';

            Habilitacion::where('examen_id', $examen->id)
                ->where('estudiante_id', $estudiante->getKey())
                ->update([
                    'estado' => 'inhabilitado',
                    'motivo_inhabilitacion_id' => $motivo->id,
                    'observacion' => mb_substr($observacion, 0, 500),
                ]);
        }

        return $sinFotoValida->count();
    }
}
