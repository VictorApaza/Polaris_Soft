<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Services\FotoEstudianteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Fotos de los estudiantes: verlas (personal) y revisarlas (docente / administrador). */
class FotoEstudianteController extends Controller
{
    /** Muestra la foto a quien tenga sesión de personal (administrador, docente o control). */
    public function ver(Estudiante $estudiante)
    {
        abort_unless($estudiante->foto_path && Storage::disk('local')->exists($estudiante->foto_path), 404);

        return Storage::disk('local')->response($estudiante->foto_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** Pantalla del docente: fotos de los estudiantes de un examen, para aprobarlas u observarlas. */
    public function revision(Request $request, Examen $examen)
    {
        abort_unless($examen->permiteA($request->user()), 403, 'Ese examen no es de una de tus materias.');

        $examen->loadMissing(['asignatura', 'materia']);
        $ids = Habilitacion::candidatosIds($examen);
        $filtro = $request->string('f')->toString();

        $estudiantes = Estudiante::whereIn('id_estudiante', $ids)
            ->when(array_key_exists($filtro, Estudiante::FOTO_ESTADOS), fn ($q) => $q->where('foto_estado', $filtro))
            ->buscar($request->string('q')->toString())
            ->orderBy('apellidos')->orderBy('nombres')
            ->paginate(12)->withQueryString();

        $conteos = Estudiante::whereIn('id_estudiante', $ids)
            ->selectRaw('foto_estado, COUNT(*) as total')
            ->groupBy('foto_estado')
            ->pluck('total', 'foto_estado');

        $habilitaciones = Habilitacion::where('examen_id', $examen->id)
            ->whereIn('estudiante_id', $estudiantes->pluck('id_estudiante'))
            ->pluck('estado', 'estudiante_id');

        return view('examenes.fotos', [
            'examen' => $examen,
            'estudiantes' => $estudiantes,
            'conteos' => $conteos,
            'totalCandidatos' => count($ids),
            'habilitaciones' => $habilitaciones,
            'filtro' => $filtro,
            'plazoVencido' => now()->gte($examen->limiteFoto()),
            'limite' => $examen->limiteFoto(),
        ]);
    }

    public function aprobar(Request $request, Estudiante $estudiante)
    {
        if (! $estudiante->tiene_foto) {
            return back()->with('error', 'Ese estudiante todavía no subió su foto.');
        }

        $estudiante->forceFill([
            'foto_estado' => 'APROBADA',
            'foto_observacion' => null,
            'foto_revisada_at' => now(),
            'foto_revisada_por' => $request->user()->id,
        ])->save();

        return back()->with('status', "Foto de {$estudiante->nombre_completo} aprobada.");
    }

    public function observar(Request $request, Estudiante $estudiante)
    {
        $data = $request->validate([
            'observacion' => ['required', 'string', 'min:5', 'max:300'],
        ], [
            'observacion.required' => 'Escribe qué está mal con la foto (por ejemplo: "está borrosa" o "no se ve el rostro").',
            'observacion.min' => 'Explica un poco más qué está mal con la foto.',
        ]);

        if (! $estudiante->tiene_foto) {
            return back()->with('error', 'Ese estudiante todavía no subió su foto.');
        }

        $estudiante->forceFill([
            'foto_estado' => 'OBSERVADA',
            'foto_observacion' => $data['observacion'],
            'foto_revisada_at' => now(),
            'foto_revisada_por' => $request->user()->id,
        ])->save();

        return back()->with('status', "Foto de {$estudiante->nombre_completo} observada: deberá subir otra antes del plazo.");
    }

    /** Pasado el plazo: inhabilita a los habilitados sin foto válida. */
    public function inhabilitarSinFoto(Request $request, Examen $examen, FotoEstudianteService $servicio)
    {
        abort_unless($examen->permiteA($request->user()), 403, 'Ese examen no es de una de tus materias.');

        if (now()->lt($examen->limiteFoto())) {
            $limite = $examen->limiteFoto()->format('d/m/Y H:i');

            return back()->with('error', "Todavía no venció el plazo para subir fotos (vence el {$limite}).");
        }

        $n = $servicio->inhabilitarSinFoto($examen);

        return back()->with('status', $n
            ? "{$n} estudiante(s) quedaron inhabilitados por no tener una foto válida."
            : 'Todos los estudiantes habilitados tienen una foto válida.');
    }
}
