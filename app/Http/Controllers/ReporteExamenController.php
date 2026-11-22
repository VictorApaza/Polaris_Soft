<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Services\ReporteExamenService;
use App\Support\ExportadorTabla;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Sprint 5 · Reportes exportables de un examen (para administrador y docente del examen):
 *   RQ16 asistencia · RQ17 ausentes · RQ18 horarios de ingreso y cantidad de registrados · RQ19 intentos a un examen que no corresponde.
 * Cada uno se ve en pantalla y se descarga con ?formato=pdf o ?formato=xlsx.
 */
class ReporteExamenController extends Controller
{
    private const POR_PAGINA = 50;

    public function __construct(private readonly ReporteExamenService $reportes)
    {
    }

    /** RQ16 */
    public function asistencia(Request $request, Examen $examen)
    {
        $this->autorizar($request, $examen);
        $datos = $this->reportes->asistencia($examen);
        $r = $datos['resumen'];

        if ($formato = $this->formato($request)) {
            return $this->descargar($formato, 'asistencia', $examen, [
                'titulo' => 'Reporte de asistencia',
                'meta' => $this->meta($examen),
                'resumen' => [
                    'Habilitados' => $r['habilitados'], 'Ingresaron' => $r['ingresaron'], 'Ausentes' => $r['ausentes'],
                    'Expulsados' => $r['expulsados'], '% asistencia' => number_format($r['porcentaje'], 1, ',', '').' %',
                ],
                'secciones' => [[
                    'titulo' => 'Estudiantes que ingresaron ('.count($datos['filas']).')',
                    'columnas' => ['N°', 'Hora de ingreso', 'Código', 'Cédula', 'Estudiante', 'Carrera', 'Método', 'Aula', 'Controlador', 'Estado'],
                    'filas' => array_map(fn ($f) => [
                        $f['n'], $f['fecha_hora'], $f['codigo'], $f['ci'], $f['nombre'], $f['carrera'], $f['metodo'], $f['ambiente'], $f['controlador'],
                        $f['estado'].($f['detalle'] ? ' — '.$f['detalle'] : ''),
                    ], $datos['filas']),
                    'alinear' => ['right'] + array_fill(1, 9, 'left'),
                    'vacio' => 'Todavía nadie registró ingreso en este examen.',
                ]],
            ]);
        }

        return view('reportes.asistencia', [
            'examen' => $examen,
            'resumen' => $r,
            'filas' => $this->paginar($datos['filas'], $request),
        ]);
    }

    /** RQ17 */
    public function ausentes(Request $request, Examen $examen)
    {
        $this->autorizar($request, $examen);
        $datos = $this->reportes->ausentes($examen);
        $r = $datos['resumen'];

        if ($formato = $this->formato($request)) {
            return $this->descargar($formato, 'ausentes', $examen, [
                'titulo' => 'Reporte de estudiantes ausentes',
                'meta' => $this->meta($examen),
                'resumen' => [
                    'Habilitados' => $r['habilitados'], 'Ingresaron (habilitados)' => $r['presentes'], 'Ausentes' => $r['ausentes'],
                    '% ausentismo' => number_format($r['porcentaje'], 1, ',', '').' %',
                ],
                'secciones' => [[
                    'titulo' => 'Habilitados que no ingresaron ('.count($datos['filas']).')',
                    'columnas' => ['N°', 'Código', 'Cédula', 'Estudiante', 'Carrera', 'Correo institucional', 'Foto'],
                    'filas' => array_map(fn ($f) => [$f['n'], $f['codigo'], $f['ci'], $f['nombre'], $f['carrera'], $f['correo'], $f['foto']], $datos['filas']),
                    'alinear' => ['right'] + array_fill(1, 6, 'left'),
                    'vacio' => 'Todos los habilitados ingresaron (o todavía no hay habilitados).',
                ]],
            ]);
        }

        return view('reportes.ausentes', [
            'examen' => $examen,
            'resumen' => $r,
            'filas' => $this->paginar($datos['filas'], $request),
        ]);
    }

    /** RQ18 */
    public function horarios(Request $request, Examen $examen)
    {
        $this->autorizar($request, $examen);
        $request->validate(['franja' => ['nullable', 'integer', 'in:'.implode(',', ReporteExamenService::FRANJAS_VALIDAS)]]);

        $otros = Examen::with(['asignatura', 'materia'])->deDocente($request->user())
            ->where('examenes.id', '!=', $examen->id)
            ->whereDate('fecha', '<=', now()->toDateString())   // solo exámenes ya realizados: los futuros no tienen registrados
            ->orderByDesc('fecha')->orderByDesc('hora_inicio')->limit(8)->get();

        $datos = $this->reportes->horarios($examen, (int) $request->input('franja', 15), $otros);
        $r = $datos['resumen'];

        if ($formato = $this->formato($request)) {
            return $this->descargar($formato, 'horarios', $examen, [
                'titulo' => 'Horarios de ingreso y estudiantes registrados',
                'meta' => $this->meta($examen) + ['Franjas de' => $datos['franja_min'].' minutos'],
                'resumen' => [
                    'Habilitados' => $r['habilitados'], 'Registrados' => $r['registrados'],
                    'Antes del inicio' => $r['antes_del_inicio'], 'Después del inicio' => $r['despues_del_inicio'],
                    'Primer ingreso' => $r['primer_ingreso'], 'Último ingreso' => $r['ultimo_ingreso'],
                    'Hora pico' => $r['hora_pico'], 'Llegada promedio' => $r['llegada_promedio'],
                ],
                'secciones' => [
                    [
                        'titulo' => 'Ingresos por franja horaria',
                        'columnas' => ['Franja', 'Estudiantes que ingresaron', 'Acumulado', '% acumulado'],
                        'filas' => array_map(fn ($f) => [$f['franja'], $f['cantidad'], $f['acumulado'], number_format($f['porcentaje_acumulado'], 1, ',', '').' %'], $datos['franjas']),
                        'alinear' => ['left', 'right', 'right', 'right'],
                        'vacio' => 'Todavía nadie registró ingreso en este examen.',
                    ],
                    [
                        'titulo' => 'Ingresos por aula',
                        'columnas' => ['Aula', 'Estudiantes', '%'],
                        'filas' => array_map(fn ($f) => [$f['ambiente'], $f['cantidad'], number_format($f['porcentaje'], 1, ',', '').' %'], $datos['por_ambiente']),
                        'alinear' => ['left', 'right', 'right'],
                        'vacio' => 'Sin ingresos.',
                    ],
                    [
                        'titulo' => 'Comparativo de estudiantes registrados por examen',
                        'columnas' => ['Examen', 'Fecha y hora', 'Aula', 'Habilitados', 'Registrados', '% asistencia', 'Primer ingreso', 'Último ingreso'],
                        'filas' => array_map(fn ($f) => [
                            $f['examen'].($f['actual'] ? ' (este examen)' : ''), $f['fecha'], $f['aula'], $f['habilitados'], $f['registrados'],
                            number_format($f['porcentaje'], 1, ',', '').' %', $f['primer_ingreso'], $f['ultimo_ingreso'],
                        ], $datos['comparativo']),
                        'alinear' => ['left', 'left', 'left', 'right', 'right', 'right', 'left', 'left'],
                        'vacio' => 'No hay otros exámenes para comparar.',
                    ],
                ],
            ]);
        }

        return view('reportes.horarios', ['examen' => $examen, 'datos' => $datos, 'resumen' => $r]);
    }

    /** RQ19 */
    public function intentos(Request $request, Examen $examen)
    {
        $this->autorizar($request, $examen);
        $datos = $this->reportes->intentosIndebidos($examen);
        $r = $datos['resumen'];

        if ($formato = $this->formato($request)) {
            return $this->descargar($formato, 'intentos-indebidos', $examen, [
                'titulo' => 'Intentos de ingreso a un examen que no les corresponde',
                'meta' => $this->meta($examen),
                'resumen' => [
                    'Intentos' => $r['intentos'], 'Estudiantes distintos' => $r['estudiantes'], 'Reincidentes' => $r['reincidentes'],
                    'Primer intento' => $r['primer_intento'], 'Último intento' => $r['ultimo_intento'],
                ],
                'secciones' => [[
                    'titulo' => 'Intentos registrados ('.count($datos['filas']).')',
                    'columnas' => ['N°', 'Fecha', 'Hora', 'Código', 'Cédula', 'Estudiante', 'Método', 'Dato ingresado', 'Aula', 'Controlador', 'Intento n.º', 'Examen que sí le corresponde'],
                    'filas' => array_map(fn ($f) => [
                        $f['n'], $f['fecha'], $f['hora'], $f['codigo'], $f['ci'], $f['nombre'], $f['metodo'], $f['dato'], $f['ambiente'], $f['controlador'], $f['intento'], $f['le_corresponde'],
                    ], $datos['filas']),
                    'alinear' => ['right', 'left', 'left', 'left', 'left', 'left', 'left', 'left', 'left', 'left', 'right', 'left'],
                    'vacio' => 'Nadie intentó ingresar a este examen sin corresponderle.',
                ]],
            ]);
        }

        return view('reportes.intentos', [
            'examen' => $examen,
            'resumen' => $r,
            'filas' => $this->paginar($datos['filas'], $request),
        ]);
    }

    // ------------------------------------------------------------------

    private function autorizar(Request $request, Examen $examen): void
    {
        abort_unless($examen->permiteA($request->user()), 403, 'Ese examen no es de una de tus materias.');
        $examen->loadMissing(['asignatura', 'materia']);
    }

    /** "pdf" | "xlsx" | null (null = verlo en pantalla). Un formato desconocido es un error 422. */
    private function formato(Request $request): ?string
    {
        $formato = $request->query('formato');
        if ($formato === null || $formato === '') {
            return null;
        }
        abort_unless(in_array($formato, ExportadorTabla::FORMATOS, true), 422, 'Formato no válido: usa pdf o xlsx.');

        return $formato;
    }

    private function descargar(string $formato, string $tipo, Examen $examen, array $reporte)
    {
        return ExportadorTabla::descargar(
            $formato,
            ExportadorTabla::nombreArchivo('reporte', $tipo, $examen->nombre, $examen->fecha->format('Y-m-d')),
            $reporte,
        );
    }

    private function meta(Examen $examen): array
    {
        return [
            'Examen' => $examen->nombre,
            'Carrera' => $examen->carrera ?: '—',
            'Fecha y hora' => $examen->fecha->format('d/m/Y').' '.$examen->hora,
            'Aula' => (string) $examen->ambiente,
        ];
    }

    private function paginar(array $filas, Request $request): LengthAwarePaginator
    {
        $pagina = max(1, (int) $request->query('page', 1));

        return (new LengthAwarePaginator(
            array_slice($filas, ($pagina - 1) * self::POR_PAGINA, self::POR_PAGINA),
            count($filas),
            self::POR_PAGINA,
            $pagina,
            ['path' => $request->url(), 'query' => $request->query()],
        ));
    }
}
