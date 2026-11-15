<?php

namespace App\Http\Controllers;

use App\Models\Docente;
use App\Models\Estudiante;
use App\Services\QrEstudianteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * RQ29: carga de fotografías por el personal (administrador o docente) y consulta de la foto de un estudiante.
 *
 *  - Carga individual: código universitario + foto.
 *  - Carga masiva: un ZIP donde cada archivo se llama como el código universitario (202400034.jpg).
 *    Devuelve cuántas se cargaron y la lista de archivos NO asociados (con el motivo).
 *  - Consulta: por código universitario, CI o QR; solo personal con sesión (el docente, solo de sus estudiantes).
 *
 * Las fotos se guardan en el disco privado (storage/app/private/fotos_estudiantes), nunca en public/.
 * Solo JPG o PNG, máximo config('sciem.foto_max_kb') (2 MB por defecto).
 */
class FotoCargaController extends Controller
{
    private const MAX_ARCHIVOS_ZIP = 1000;

    public function index()
    {
        return view('fotos.cargar');
    }

    public function individual(Request $request)
    {
        $request->validate([
            'codigo_universitario' => ['required', 'digits:9'],
            'foto' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:'.config('sciem.foto_max_kb')],
        ], [
            'codigo_universitario.required' => 'Escribe el código universitario del estudiante.',
            'codigo_universitario.digits' => 'El código universitario debe tener 9 dígitos.',
            'foto.required' => 'Elige la foto.',
            'foto.mimes' => 'La foto debe ser JPG o PNG.',
            'foto.max' => 'La foto pesa demasiado (máximo '.(config('sciem.foto_max_kb') / 1024).' MB).',
        ]);

        $estudiante = Estudiante::where('codigo_universitario', $request->codigo_universitario)->first();
        if (! $estudiante) {
            return back()->withInput()->withErrors(['codigo_universitario' => 'No existe un estudiante con ese código universitario.']);
        }
        if (! $this->puedeGestionar($request, $estudiante)) {
            return back()->withInput()->withErrors(['codigo_universitario' => 'Ese estudiante no está en ninguno de tus grupos.']);
        }

        $error = $this->guardarFoto($estudiante, (string) file_get_contents($request->file('foto')->getRealPath()), $request);
        if ($error) {
            return back()->withInput()->withErrors(['foto' => $error]);
        }

        return back()->with('status', "Foto de {$estudiante->nombre_completo} cargada correctamente.");
    }

    public function zip(Request $request)
    {
        $request->validate([
            'archivo_zip' => ['required', 'file', 'mimes:zip', 'max:51200'],
        ], [
            'archivo_zip.required' => 'Elige el archivo ZIP con las fotos.',
            'archivo_zip.mimes' => 'El archivo debe ser un ZIP.',
            'archivo_zip.max' => 'El ZIP pesa demasiado (máximo 50 MB).',
        ]);

        if (! class_exists(ZipArchive::class)) {
            return back()->withErrors(['archivo_zip' => 'Este servidor no tiene la extensión zip de PHP activada. Carga las fotos una por una.']);
        }

        $zip = new ZipArchive();
        if ($zip->open($request->file('archivo_zip')->getRealPath()) !== true) {
            return back()->withErrors(['archivo_zip' => 'No se pudo abrir el ZIP. Verifica que no esté dañado.']);
        }
        if ($zip->numFiles > self::MAX_ARCHIVOS_ZIP) {
            $zip->close();

            return back()->withErrors(['archivo_zip' => 'El ZIP tiene demasiados archivos (máximo '.self::MAX_ARCHIVOS_ZIP.'). Divídelo en varios.']);
        }

        $maxBytes = (int) config('sciem.foto_max_kb') * 1024;
        $cargadas = 0;
        $noAsociadas = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $ruta = (string) $zip->getNameIndex($i);
            if ($ruta === '' || str_ends_with($ruta, '/') || str_contains($ruta, '__MACOSX')) {
                continue; // carpetas y basura de macOS
            }

            $archivo = basename(str_replace('\\', '/', $ruta));
            if (str_starts_with($archivo, '.')) {
                continue;
            }

            $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
            if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                $noAsociadas[] = "{$archivo}: formato no permitido (solo JPG o PNG).";
                continue;
            }

            $codigo = trim(pathinfo($archivo, PATHINFO_FILENAME));
            if (! preg_match('/^\d{9}$/', $codigo)) {
                $noAsociadas[] = "{$archivo}: el nombre debe ser el código universitario de 9 dígitos (ej. 202400034.jpg).";
                continue;
            }

            $estudiante = Estudiante::where('codigo_universitario', $codigo)->first();
            if (! $estudiante) {
                $noAsociadas[] = "{$archivo}: no existe un estudiante con el código {$codigo}.";
                continue;
            }
            if (! $this->puedeGestionar($request, $estudiante)) {
                $noAsociadas[] = "{$archivo}: el estudiante {$codigo} no está en ninguno de tus grupos.";
                continue;
            }

            $tamano = (int) ($zip->statIndex($i)['size'] ?? 0);
            if ($tamano > $maxBytes) {
                $noAsociadas[] = "{$archivo}: pesa más de ".(config('sciem.foto_max_kb') / 1024).' MB.';
                continue;
            }

            $error = $this->guardarFoto($estudiante, (string) $zip->getFromIndex($i), $request);
            if ($error) {
                $noAsociadas[] = "{$archivo}: {$error}";
                continue;
            }
            $cargadas++;
        }
        $zip->close();

        return back()->with('resultado_zip', ['cargadas' => $cargadas, 'no_asociadas' => $noAsociadas]);
    }

    /**
     * Consulta la foto de un estudiante identificado por código universitario, CI o QR.
     * Devuelve la imagen; con ?formato=json (o Accept: application/json) devuelve sus datos y la dirección de la foto.
     */
    public function consultar(Request $request)
    {
        $request->validate([
            'codigo_universitario' => ['nullable', 'digits:9'],
            'ci' => ['nullable', 'string', 'max:20'],
            'qr' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($request->filled('codigo_universitario')) {
            $estudiante = Estudiante::where('codigo_universitario', $request->codigo_universitario)->first();
        } elseif ($request->filled('ci')) {
            $estudiante = Estudiante::where('documento_identidad', trim($request->ci))->first();
        } elseif ($request->filled('qr')) {
            $estudiante = app(QrEstudianteService::class)->validarToken($request->qr);
        } else {
            return response()->json(['mensaje' => 'Indica codigo_universitario, ci o qr.'], 422);
        }

        if (! $estudiante) {
            return response()->json(['mensaje' => 'No se encontró al estudiante.'], 404);
        }
        if (! $this->puedeGestionar($request, $estudiante)) {
            return response()->json(['mensaje' => 'Ese estudiante no está en ninguno de tus grupos.'], 403);
        }
        if (! $estudiante->foto_path || ! Storage::disk('local')->exists($estudiante->foto_path)) {
            return response()->json(['mensaje' => 'El estudiante no tiene foto registrada.'], 404);
        }

        if ($request->expectsJson() || $request->query('formato') === 'json') {
            return new JsonResponse([
                'id_estudiante' => $estudiante->getKey(),
                'codigo_universitario' => $estudiante->codigo_universitario,
                'ci' => $estudiante->documento_identidad,
                'nombre' => $estudiante->nombre_completo,
                'foto_estado' => $estudiante->foto_estado,
                'foto_url' => $estudiante->foto_url,
            ]);
        }

        return Storage::disk('local')->response($estudiante->foto_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** El administrador y el personal de control gestionan a todos; el docente, solo a los de sus grupos. */
    private function puedeGestionar(Request $request, Estudiante $estudiante): bool
    {
        if ($request->user()->rol !== 'docente') {
            return true;
        }

        $docente = Docente::paraUsuario($request->user());

        return $docente && in_array((int) $estudiante->getKey(), $docente->estudianteIds(), true);
    }

    /**
     * Valida que sea de verdad una imagen JPG/PNG de tamaño razonable y la guarda en el disco privado.
     * Devuelve el texto del error o null si todo salió bien.
     */
    private function guardarFoto(Estudiante $estudiante, string $contenido, Request $request): ?string
    {
        if ($contenido === '') {
            return 'el archivo está vacío.';
        }
        if (strlen($contenido) > (int) config('sciem.foto_max_kb') * 1024) {
            return 'pesa más de '.(config('sciem.foto_max_kb') / 1024).' MB.';
        }

        $info = @getimagesizefromstring($contenido);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return 'no es una imagen JPG o PNG válida.';
        }
        if ($info[0] < 200 || $info[1] < 200) {
            return 'la foto es muy pequeña (mínimo 200 x 200 píxeles).';
        }

        $extension = $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
        $ruta = 'fotos_estudiantes/'.$estudiante->getKey().'_'.time().'_'.random_int(100, 999).'.'.$extension;
        Storage::disk('local')->put($ruta, $contenido);

        if ($estudiante->foto_path) {
            Storage::disk('local')->delete($estudiante->foto_path);
        }

        // La foto cargada por el personal ya queda aprobada (no hace falta que un docente la revise).
        $estudiante->forceFill([
            'foto_path' => $ruta,
            'foto_estado' => 'APROBADA',
            'foto_observacion' => null,
            'foto_subida_at' => now(),
            'foto_revisada_at' => now(),
            'foto_revisada_por' => $request->user()->id,
        ])->save();

        return null;
    }
}
