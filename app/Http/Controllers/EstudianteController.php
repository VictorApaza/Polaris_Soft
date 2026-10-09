<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Materia;
use App\Support\Catalogo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EstudianteController extends Controller
{
    /** Letras, espacios, acentos y ñ (sin números ni símbolos). */
    private const REGEX_ALFABETICO = '/^[\pL\s]+$/u';

    /** Código universitario: exactamente 9 dígitos, empieza con el año (p. ej. 202400034). */
    private const REGEX_CODIGO = '/^[0-9]{9}$/';

    /** Complemento opcional del CI: hasta 2 caracteres, letras y/o números. */
    private const REGEX_COMPLEMENTO = '/^[A-Za-z0-9]{1,2}$/';

    /** Carnet de identidad: solo dígitos, entre 5 y 10. */
    private const REGEX_CARNET = '/^[0-9]{5,10}$/';

    public function index(Request $request)
    {
        $estudiantes = Estudiante::query()
            ->buscar($request->string('buscar')->toString())
            ->when($request->filled('carrera'), fn ($q) => $q->where('carrera', $request->carrera))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', strtoupper($request->estado)))
            ->orderBy('apellidos')
            ->paginate(5)
            ->withQueryString();

        $stats = [
            'total' => Estudiante::count(),
            'habilitados' => Estudiante::where('estado', 'ACTIVO')->count(),
            'inactivos' => Estudiante::where('estado', '<>', 'ACTIVO')->count(),
        ];

        $carreras = collect(Catalogo::FACULTADES)
            ->flatten()
            ->merge(Estudiante::whereNotNull('carrera')->distinct()->pluck('carrera'))
            ->unique()->sort()->values();

        return view('estudiantes.index', [
            'estudiantes' => $estudiantes, 'stats' => $stats, 'carreras' => $carreras,
            'facultades' => Catalogo::FACULTADES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $data['estado'] = 'ACTIVO';

        Estudiante::create($data);

        return redirect()->route('estudiantes.index')->with('status', 'Estudiante registrado correctamente.');
    }

    public function update(Request $request, Estudiante $estudiante)
    {
        $estudiante->update($this->validar($request, $estudiante));

        return redirect()->route('estudiantes.index')->with('status', 'Estudiante actualizado correctamente.');
    }

    public function destroy(Estudiante $estudiante)
    {
        $estudiante->delete();

        return redirect()->route('estudiantes.index')->with('status', 'Estudiante eliminado.');
    }

    private function validar(Request $request, ?Estudiante $actual = null): array
    {
        $id = $actual?->getKey();

        $data = $request->validate([
            'facultad' => ['required', Rule::in(array_keys(Catalogo::FACULTADES))],
            'carrera' => ['required', 'string', 'max:120'],
            'codigo_universitario' => [
                'required', 'regex:'.self::REGEX_CODIGO,
                Rule::unique('estudiante', 'codigo_universitario')->ignore($id, 'id_estudiante'),
            ],
            'documento_identidad' => [
                'required', 'regex:'.self::REGEX_CARNET,
                Rule::unique('estudiante', 'documento_identidad')->ignore($id, 'id_estudiante'),
            ],
            'ci_complemento' => ['nullable', 'regex:'.self::REGEX_COMPLEMENTO],
            'nombres' => ['required', 'string', 'max:100', 'regex:'.self::REGEX_ALFABETICO],
            'apellidos' => ['required', 'string', 'max:100', 'regex:'.self::REGEX_ALFABETICO],
            'estado' => ['required', Rule::in(['ACTIVO', 'OBSERVADO', 'INACTIVO'])],
        ], [
            'codigo_universitario.unique' => 'Ya existe un estudiante con ese código.',
            'codigo_universitario.regex' => 'El código debe tener exactamente 9 dígitos numéricos (año + número).',
            'documento_identidad.unique' => 'Ya existe un estudiante con ese CI / DNI.',
            'documento_identidad.regex' => 'El CI debe ser numérico, entre 5 y 10 dígitos.',
            'ci_complemento.regex' => 'El complemento admite máximo 2 caracteres (letras y/o números).',
            'nombres.regex' => 'El nombre solo puede contener letras.',
            'apellidos.regex' => 'El apellido solo puede contener letras.',
            'facultad.required' => 'Seleccione la facultad.',
        ]);

        if (! in_array($data['carrera'], Catalogo::carrerasDe($data['facultad']), true)) {
            abort(422, 'La carrera seleccionada no pertenece a la facultad elegida.');
        }

        $data['estado'] = strtoupper($data['estado']);
        // El correo institucional siempre se deriva del código universitario; nunca se confía en texto libre.
        $data['correo_institucional'] = $data['codigo_universitario'].'@universidad.edu';

        return $data;
    }

    // ==================================================================
    //  Importación masiva de estudiantes desde CSV
    // ==================================================================

    /** Muestra el formulario de importación (facultad, carrera, materia, grupo y archivo). */
    public function importarForm()
    {
        $materias = Materia::whereNotNull('carrera')->orderBy('nombre')->get(['id', 'nombre', 'sigla', 'carrera']);
        $grupos = Grupo::with('docente:id,nombre,apellido')->orderBy('nombre')->get(['id', 'nombre', 'materia_id', 'docente_id']);

        return view('estudiantes.importar', [
            'facultades' => Catalogo::FACULTADES,
            'materias' => $materias,
            'grupos' => $grupos,
        ]);
    }

    /**
     * Procesa el archivo CSV: valida TODAS las filas primero (todo o nada) y,
     * si no hay errores, crea los estudiantes y los asigna a la materia/grupo elegidos.
     */
    public function importar(Request $request)
    {
        $request->validate([
            'facultad' => ['required', Rule::in(array_keys(Catalogo::FACULTADES))],
            'carrera' => ['required', 'string'],
            'materia_id' => ['required', 'exists:materias,id'],
            'grupo_id' => ['required', 'exists:grupos,id'],
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [], ['archivo' => 'archivo']);

        if (! in_array($request->carrera, Catalogo::carrerasDe($request->facultad), true)) {
            return back()->withInput()->withErrors(['carrera' => 'La carrera no pertenece a la facultad elegida.']);
        }

        $grupo = Grupo::findOrFail($request->grupo_id);
        if ((int) $grupo->materia_id !== (int) $request->materia_id) {
            return back()->withInput()->withErrors(['grupo_id' => 'El grupo elegido no pertenece a la materia seleccionada.']);
        }

        $filas = $this->leerCsv($request->file('archivo'));

        if ($filas === null) {
            return back()->withInput()->withErrors(['archivo' => 'No se pudo leer el archivo. Verifique que sea un CSV válido con las columnas: codigo_universitario, ci, ci_complemento, nombres, apellidos.']);
        }

        if (empty($filas)) {
            return back()->withInput()->withErrors(['archivo' => 'El archivo no contiene filas de datos.']);
        }

        // --- Validación completa de TODAS las filas antes de guardar nada ---
        $errores = [];
        $codigosEnArchivo = [];

        foreach ($filas as $i => $fila) {
            $numeroFila = $i + 2; // +2: encabezado (fila 1) + índice base 0
            $codigo = trim((string) ($fila['codigo_universitario'] ?? ''));
            $complemento = trim((string) ($fila['ci_complemento'] ?? '')) ?: null;
            $ci = trim((string) ($fila['ci'] ?? ''));
            $nombres = trim((string) ($fila['nombres'] ?? ''));
            $apellidos = trim((string) ($fila['apellidos'] ?? ''));

            if (! preg_match(self::REGEX_CODIGO, $codigo)) {
                $errores[] = "Fila {$numeroFila}, columna 'codigo_universitario': debe tener exactamente 9 dígitos.";
            } elseif (isset($codigosEnArchivo[$codigo])) {
                $errores[] = "Fila {$numeroFila}, columna 'codigo_universitario': el código {$codigo} está repetido en el archivo (fila {$codigosEnArchivo[$codigo]}).";
            } elseif (Estudiante::where('codigo_universitario', $codigo)->exists()) {
                $errores[] = "Fila {$numeroFila}, columna 'codigo_universitario': el código {$codigo} ya está registrado en el sistema.";
            } else {
                $codigosEnArchivo[$codigo] = $numeroFila;
            }

            if ($complemento !== null && ! preg_match(self::REGEX_COMPLEMENTO, $complemento)) {
                $errores[] = "Fila {$numeroFila}, columna 'ci_complemento': máximo 2 caracteres, letras y/o números.";
            }

            if (! preg_match(self::REGEX_CARNET, $ci)) {
                $errores[] = "Fila {$numeroFila}, columna 'ci': debe ser numérico, entre 5 y 10 dígitos.";
            } elseif (Estudiante::where('documento_identidad', $ci)->exists()) {
                $errores[] = "Fila {$numeroFila}, columna 'ci': el CI {$ci} ya está registrado en el sistema.";
            }

            if ($nombres === '' || ! preg_match(self::REGEX_ALFABETICO, $nombres)) {
                $errores[] = "Fila {$numeroFila}, columna 'nombres': es obligatorio y solo puede contener letras.";
            }

            if ($apellidos === '' || ! preg_match(self::REGEX_ALFABETICO, $apellidos)) {
                $errores[] = "Fila {$numeroFila}, columna 'apellidos': es obligatorio y solo puede contener letras.";
            }
        }

        if (! empty($errores)) {
            return back()->withInput()->withErrors(['importacion' => $errores]);
        }

        // --- Todo válido: se crean los estudiantes y sus asignaciones ---
        $creados = DB::transaction(function () use ($filas, $request, $grupo) {
            $total = 0;

            foreach ($filas as $fila) {
                $codigo = trim((string) $fila['codigo_universitario']);

                $estudiante = Estudiante::create([
                    'facultad' => $request->facultad,
                    'carrera' => $request->carrera,
                    'codigo_universitario' => $codigo,
                    'ci_complemento' => trim((string) ($fila['ci_complemento'] ?? '')) ?: null,
                    'documento_identidad' => trim((string) $fila['ci']),
                    'nombres' => trim((string) $fila['nombres']),
                    'apellidos' => trim((string) $fila['apellidos']),
                    'correo_institucional' => $codigo.'@universidad.edu',
                    'estado' => 'ACTIVO',
                ]);

                Asignacion::create([
                    'estudiante_id' => $estudiante->getKey(),
                    'materia_id' => $request->materia_id,
                    'grupo_id' => $grupo->id,
                    'docente_id' => $grupo->docente_id,
                ]);

                $total++;
            }

            return $total;
        });

        return redirect()->route('estudiantes.index')
            ->with('status', "Se importaron {$creados} estudiantes y se asignaron a la materia y grupo seleccionados.");
    }

    /** Lee un CSV subido y lo devuelve como array de filas asociativas (null si no se pudo leer). */
    private function leerCsv($archivo): ?array
    {
        $manejador = fopen($archivo->getRealPath(), 'r');
        if (! $manejador) {
            return null;
        }

        $encabezado = fgetcsv($manejador, 0, ',');
        if (! $encabezado) {
            fclose($manejador);

            return null;
        }

        $encabezado = array_map(fn ($c) => strtolower(trim((string) $c)), $encabezado);
        $filas = [];

        while (($linea = fgetcsv($manejador, 0, ',')) !== false) {
            if (count($linea) === 1 && trim((string) $linea[0]) === '') {
                continue; // línea vacía
            }
            $linea = array_slice(array_pad($linea, count($encabezado), null), 0, count($encabezado));
            $filas[] = array_combine($encabezado, $linea);
        }

        fclose($manejador);

        return $filas;
    }

    

public function vistaIngreso()
{
    return view('ingreso.index');
}

public function verificarIngreso(Request $request)
{
    $request->validate([
        'modo' => ['required', 'in:codigo,ci'],
        'codigo_universitario' => [
            'required_if:modo,codigo',
            'nullable',
            'regex:/^[0-9]{9}$/',
        ],
        'ci' => [
            'required_if:modo,ci',
            'nullable',
            'regex:/^[0-9]{5,10}$/',
        ],
        'ci_complemento' => [
            'nullable',
            'regex:/^[A-Za-z0-9]{1,2}$/',
        ],
    ], [
        'modo.required' => 'Selecciona un método de búsqueda.',
        'modo.in' => 'El método de búsqueda no es válido.',
        'codigo_universitario.required_if' =>
            'Introduce el código universitario.',
        'codigo_universitario.regex' =>
            'El código universitario debe tener 9 dígitos.',
        'ci.required_if' => 'Introduce la cédula de identidad.',
        'ci.regex' =>
            'El C.I. debe contener entre 5 y 10 dígitos.',
        'ci_complemento.regex' =>
            'El complemento debe tener entre 1 y 2 caracteres alfanuméricos.',
    ]);

    if ($request->modo === 'codigo') {
        $estudiante = \App\Models\Estudiante::where(
            'codigo_universitario',
            trim($request->codigo_universitario)
        )->first();
    } else {
        $ci = trim($request->ci);
        $complemento = strtoupper(
            trim($request->ci_complemento ?? '')
        );

        $consulta = \App\Models\Estudiante::where(
            'documento_identidad',
            $ci
        );

        if ($complemento === '') {
            $consulta->where(function ($query) {
                $query->whereNull('ci_complemento')
                      ->orWhere('ci_complemento', '');
            });
        } else {
            $consulta->where('ci_complemento', $complemento);
        }

        $estudiante = $consulta->first();
    }

    if (!$estudiante) {
        return response()->json([
            'encontrado' => false,
            'mensaje' => 'No se encontró un estudiante con los datos proporcionados.',
        ], 404);
    }

    return response()->json([
        'encontrado' => true,
        'mensaje' => 'Estudiante encontrado correctamente.',
        'estudiante' => [
            'id_estudiante' => $estudiante->id_estudiante,
            'nombres' => $estudiante->nombres,
            'apellidos' => $estudiante->apellidos,
            'codigo_universitario' => $estudiante->codigo_universitario,
            'documento_identidad' => $estudiante->documento_identidad,
            'ci_complemento' => $estudiante->ci_complemento,
            'facultad' => $estudiante->facultad,
            'carrera' => $estudiante->carrera,
            'estado' => $estudiante->estado,
        ],
    ]);
}
}
