<?php

namespace App\Http\Controllers;

use App\Models\Incidencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IncidenciaController extends Controller
{
    /** El personal de control reporta una incidencia a mano (foto no coincide, suplantación, trampa…). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'examen_id' => ['required', 'integer', 'exists:examenes,id'],
            'estudiante_id' => ['nullable', 'integer', 'exists:estudiante,id_estudiante'],
            'tipo' => ['required', Rule::in(['SUPLANTACION_SOSPECHADA', 'FOTO_NO_COINCIDE', 'TRAMPA', 'OTRO'])],
            'metodo' => ['nullable', Rule::in(array_keys(Incidencia::METODOS))],
            'detalle' => ['nullable', 'string', 'max:500', 'required_if:tipo,OTRO'],
        ], [
            'detalle.required_if' => 'Describe la incidencia.',
        ]);

        $incidencia = Incidencia::create($data + [
            'controlador_id' => $request->user()->id,
            'fecha_hora' => now(),
            'estado' => 'ABIERTA',
        ]);

        return response()->json([
            'mensaje' => 'Incidencia registrada. El docente podrá verla en el reporte del examen.',
            'id' => $incidencia->id,
        ], 201);
    }

    /** El docente o administrador marca la incidencia como atendida. */
    public function revisar(Request $request, Incidencia $incidencia)
    {
        $incidencia->update([
            'estado' => 'REVISADA',
            'revisada_por' => $request->user()->id,
            'revisada_at' => now(),
        ]);

        return back()->with('status', 'Incidencia marcada como revisada.');
    }
}
