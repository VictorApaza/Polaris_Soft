<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\Incidencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class IncidenciaController extends Controller
{
    private const TIPOS_REGISTRABLES = ['SUPLANTACION_SOSPECHADA', 'SUPLANTACION_CONFIRMADA', 'FOTO_NO_COINCIDE', 'TRAMPA', 'OTRO'];

    /**
     * Registra una incidencia. La usan:
     *  - el personal de control en la puerta (foto no coincide, suplantación: con nombre, CI y foto del suplantador);
     *  - el docente durante el examen (copia, trampa u otra falta), con su argumento y una foto de prueba.
     * Responde JSON si la llamada viene de la pantalla de verificación (fetch) y redirige si viene de un formulario.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'examen_id' => ['required', 'integer', 'exists:examenes,id'],
            'estudiante_id' => ['nullable', 'integer', 'exists:estudiante,id_estudiante'],
            'tipo' => ['required', Rule::in(self::TIPOS_REGISTRABLES)],
            'metodo' => ['nullable', Rule::in(array_keys(Incidencia::METODOS))],
            'detalle' => ['nullable', 'string', 'max:1000', 'required_if:tipo,OTRO,TRAMPA'],
            'evidencia' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'suplantador_nombre' => ['nullable', 'string', 'max:150', 'required_if:tipo,SUPLANTACION_CONFIRMADA'],
            'suplantador_ci' => ['nullable', 'string', 'max:20', 'required_if:tipo,SUPLANTACION_CONFIRMADA'],
            'suplantador_foto' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'required_if:tipo,SUPLANTACION_CONFIRMADA'],
        ], [
            'detalle.required_if' => 'Escribe el argumento o la descripción de la incidencia.',
            'suplantador_nombre.required_if' => 'Escribe el nombre del suplantador.',
            'suplantador_ci.required_if' => 'Escribe la cédula del suplantador.',
            'suplantador_foto.required_if' => 'Toma o sube la foto del suplantador.',
            'evidencia.image' => 'La prueba debe ser una foto (JPG, PNG o WEBP).',
            'suplantador_foto.image' => 'La foto del suplantador debe ser una imagen (JPG, PNG o WEBP).',
            'evidencia.max' => 'La foto de prueba pesa demasiado (máximo 4 MB).',
            'suplantador_foto.max' => 'La foto del suplantador pesa demasiado (máximo 4 MB).',
        ]);

        $examen = Examen::findOrFail($data['examen_id']);
        abort_unless($examen->permiteA($request->user()), 403, 'Ese examen no es de una de tus materias.');

        $datos = collect($data)->except(['evidencia', 'suplantador_foto'])->all();
        if ($request->hasFile('evidencia')) {
            $datos['evidencia_path'] = $request->file('evidencia')->store('incidencias', 'local');
        }
        if ($request->hasFile('suplantador_foto')) {
            $datos['suplantador_foto_path'] = $request->file('suplantador_foto')->store('incidencias', 'local');
        }

        $incidencia = Incidencia::create($datos + [
            'controlador_id' => $request->user()->id,
            'fecha_hora' => now(),
            'estado' => 'ABIERTA',
        ]);

        $mensaje = 'Incidencia registrada. El docente podrá verla en el reporte del examen.';

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => $mensaje, 'id' => $incidencia->id], 201);
        }

        return back()->with('status', 'Incidencia registrada con su argumento y sus pruebas. Quedó en el reporte del examen.');
    }

    /** Muestra la foto de prueba o la foto del suplantador a quien tenga permiso sobre el examen. */
    public function archivo(Request $request, Incidencia $incidencia, string $cual)
    {
        abort_unless($incidencia->examen && $incidencia->examen->permiteA($request->user()), 403);

        $ruta = $cual === 'suplantador' ? $incidencia->suplantador_foto_path : $incidencia->evidencia_path;
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404);

        return Storage::disk('local')->response($ruta, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** El docente o administrador marca la incidencia como atendida. */
    public function revisar(Request $request, Incidencia $incidencia)
    {
        abort_unless($incidencia->examen && $incidencia->examen->permiteA($request->user()), 403);

        $incidencia->update([
            'estado' => 'REVISADA',
            'revisada_por' => $request->user()->id,
            'revisada_at' => now(),
        ]);

        return back()->with('status', 'Incidencia marcada como revisada.');
    }
}
