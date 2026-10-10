<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Habilitacion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Endpoints JSON de RQ3 (carga individual y masiva de habilitaciones).
 * Requieren un token de API de un usuario administrador (ver README / instrucciones).
 */
class HabilitacionApiController extends Controller
{
    /** POST /api/habilitaciones — habilita a un solo estudiante para un examen. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'estudiante_id' => ['required', 'integer', Rule::exists('estudiante', 'id_estudiante')],
            'examen_id' => ['required', 'integer', 'exists:examenes,id'],
        ]);

        $examen = Examen::findOrFail($data['examen_id']);
        $resultado = Habilitacion::habilitar($examen, [(int) $data['estudiante_id']]);

        if (! $resultado['habilitados']) {
            return response()->json(['mensaje' => 'El estudiante no está asignado a la materia de este examen.'], 422);
        }

        $habilitacion = Habilitacion::where('estudiante_id', $data['estudiante_id'])
            ->where('examen_id', $examen->id)->first();

        return response()->json(['mensaje' => 'Estudiante habilitado.', 'habilitacion' => $habilitacion], 201);
    }

    /**
     * POST /api/habilitaciones/masiva — habilita a varios estudiantes de un solo golpe.
     * Enviar "estudiante_ids": [1,2,3]  o  "grupo_id": 4 (todos los asignados a ese grupo).
     */
    public function masiva(Request $request)
    {
        $data = $request->validate([
            'examen_id' => ['required', 'integer', 'exists:examenes,id'],
            'estudiante_ids' => ['required_without:grupo_id', 'array', 'min:1'],
            'estudiante_ids.*' => ['integer'],
            'grupo_id' => ['required_without:estudiante_ids', 'integer', 'exists:grupos,id'],
        ]);

        $examen = Examen::findOrFail($data['examen_id']);

        if (! empty($data['estudiante_ids'])) {
            $resultado = Habilitacion::habilitar($examen, array_map('intval', $data['estudiante_ids']));
        } else {
            $grupo = Grupo::findOrFail($data['grupo_id']);
            if ((int) $grupo->materia_id !== (int) $examen->materia_id) {
                return response()->json(['mensaje' => 'Ese grupo no pertenece a la materia del examen.'], 422);
            }
            $resultado = Habilitacion::habilitar($examen, Habilitacion::idsDelGrupo($examen, $grupo->id), respetarInhabilitados: true);
        }

        return response()->json([
            'mensaje' => count($resultado['habilitados']).' estudiante(s) habilitado(s).',
            'habilitados' => $resultado['habilitados'],
            'no_asignados_ignorados' => $resultado['ignorados'],
            'inhabilitados_respetados' => $resultado['respetados'],
        ], 201);
    }

    /** GET /api/habilitaciones/examen/{id} — lista de habilitados/inhabilitados de un examen. */
    public function porExamen(int $id)
    {
        $examen = Examen::findOrFail($id);

        $habilitaciones = Habilitacion::with(['estudiante:id_estudiante,nombres,apellidos,codigo_universitario', 'motivo'])
            ->where('examen_id', $examen->id)
            ->get();

        return response()->json($habilitaciones);
    }
}
