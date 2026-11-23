<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\NormaParticular;
use Illuminate\Http\Request;

/**
 * Normas particulares de un estudiante en un examen (requerimiento general 2).
 * Las registran el administrador y el docente (este último solo en los exámenes de sus materias).
 */
class NormaParticularController extends Controller
{
    /** Guarda o actualiza la norma; si el texto llega vacío, la elimina. */
    public function guardar(Request $request, Examen $examen, Estudiante $estudiante)
    {
        abort_unless($examen->permiteA($request->user()), 403, 'Ese examen no es de una de tus materias.');

        $data = $request->validate(
            ['norma' => ['nullable', 'string', 'max:1000']],
            ['norma.max' => 'La norma no puede pasar de 1000 caracteres.']
        );

        if (! Habilitacion::idsAsignados($examen, [$estudiante->getKey()])) {
            return back()->withErrors(['norma' => 'Ese estudiante no está asignado a la materia de este examen.']);
        }

        $texto = trim((string) ($data['norma'] ?? ''));

        if ($texto === '') {
            NormaParticular::where('examen_id', $examen->id)->where('estudiante_id', $estudiante->getKey())->delete();

            return back()->with('status', "Se quitó la norma particular de {$estudiante->nombre_completo}.");
        }

        NormaParticular::updateOrCreate(
            ['examen_id' => $examen->id, 'estudiante_id' => $estudiante->getKey()],
            ['norma' => $texto, 'registrado_por' => $request->user()->id]
        );

        return back()->with('status', "Norma particular guardada para {$estudiante->nombre_completo}.");
    }
}
