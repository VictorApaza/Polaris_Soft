<?php

namespace App\Http\Controllers;

use App\Models\Docente;
use App\Models\User;
use App\Support\Grados;
use App\Support\LectorTabla;
use App\Support\Plantillas;
use App\Support\Texto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DocenteController extends Controller
{
    /** Letras, espacios, acentos y ñ (sin números ni símbolos). */
    private const REGEX_ALFABETICO = '/^[\pL\s]+$/u';

    /** Carnet de identidad: solo dígitos, entre 5 y 10. */
    private const REGEX_CARNET = '/^[0-9]{5,10}$/';

    /** Complemento opcional del CI: hasta 2 caracteres, letras y/o números. */
    private const REGEX_COMPLEMENTO = '/^[A-Za-z0-9]{1,2}$/';

    public function index()
    {
        $docentes = Docente::withCount('grupos')->orderBy('nombre')->get();

        return view('docentes.index', [
            'docentes' => $docentes,
            'grados' => Grados::OPCIONES,
            'usuariosDocentes' => User::where('rol', 'docente')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request)
    {
        Docente::create($this->validar($request));

        return back()->with('success', 'Docente registrado correctamente.');
    }

    public function update(Request $request, Docente $docente)
    {
        $docente->update($this->validar($request, $docente));

        return back()->with('success', 'Docente actualizado correctamente.');
    }

    public function destroy(Docente $docente)
    {
        $docente->delete();
        return back()->with('success', 'Docente eliminado.');
    }

    // ==================================================================
    //  Importación masiva (CSV o Excel) — solo administrador
    // ==================================================================

    /** Descarga la plantilla CSV con el formato exacto que espera la importación. */
    public function plantilla()
    {
        return Plantillas::csv('plantilla_docentes.csv',
            ['nombres', 'apellidos', 'grado', 'ci', 'ci_complemento', 'correo'],
            [
                ['María', 'Ramos Quiroga', 'Doctor(a)', '4521367', '', 'maria.ramos@sciem.edu'],
                ['Luis', 'Fernández Rojas', 'Ingeniero(a)', '5123890', '1A', ''],
            ]);
    }

    /**
     * Valida TODAS las filas primero (todo o nada). Si todo está bien, crea los docentes.
     * Columnas: nombres, apellidos, grado, ci, ci_complemento (opcional), correo (opcional: vincula su cuenta de usuario).
     */
    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:2048'],
        ], [
            'archivo.required' => 'Elige el archivo CSV o Excel con los docentes.',
            'archivo.mimes' => 'El archivo debe ser CSV o Excel (.xlsx).',
        ]);

        $filas = LectorTabla::leer($request->file('archivo'));

        if ($filas === null) {
            return back()->withErrors(['importacion' => ['No se pudo leer el archivo. Usa la plantilla (CSV) o un Excel .xlsx con las columnas: nombres, apellidos, grado, ci, ci_complemento, correo.']]);
        }
        if (empty($filas)) {
            return back()->withErrors(['importacion' => ['El archivo no contiene filas de datos.']]);
        }

        $errores = [];
        $validas = [];
        $cisEnArchivo = [];

        foreach ($filas as $i => $fila) {
            $n = $i + 2; // fila 1 = encabezado
            $nombre = Texto::nombrePropio($fila['nombres'] ?? ($fila['nombre'] ?? ''));
            $apellido = Texto::nombrePropio($fila['apellidos'] ?? ($fila['apellido'] ?? ''));
            $grado = $this->normalizarGrado((string) ($fila['grado'] ?? ''));
            $ci = trim((string) ($fila['ci'] ?? ''));
            $complemento = strtoupper(trim((string) ($fila['ci_complemento'] ?? ''))) ?: null;
            $correo = strtolower(trim((string) ($fila['correo'] ?? '')));

            if ($nombre === '' || ! preg_match(self::REGEX_ALFABETICO, $nombre)) {
                $errores[] = "Fila {$n}, columna 'nombres': es obligatorio y solo puede contener letras.";
            }
            if ($apellido === '' || ! preg_match(self::REGEX_ALFABETICO, $apellido)) {
                $errores[] = "Fila {$n}, columna 'apellidos': es obligatorio y solo puede contener letras.";
            }
            if ($grado === null) {
                $errores[] = "Fila {$n}, columna 'grado': usa uno de estos: ".implode(', ', array_keys(Grados::OPCIONES)).'.';
            }
            if (! preg_match(self::REGEX_CARNET, $ci)) {
                $errores[] = "Fila {$n}, columna 'ci': debe ser numérico, entre 5 y 10 dígitos.";
            } elseif (isset($cisEnArchivo[$ci])) {
                $errores[] = "Fila {$n}, columna 'ci': el CI {$ci} está repetido en el archivo (fila {$cisEnArchivo[$ci]}).";
            } elseif (Docente::where('ci', $ci)->exists()) {
                $errores[] = "Fila {$n}, columna 'ci': el CI {$ci} ya está registrado en el sistema.";
            } else {
                $cisEnArchivo[$ci] = $n;
            }
            if ($complemento !== null && ! preg_match(self::REGEX_COMPLEMENTO, $complemento)) {
                $errores[] = "Fila {$n}, columna 'ci_complemento': máximo 2 caracteres, letras y/o números.";
            }

            $userId = null;
            if ($correo !== '') {
                $userId = User::where('email', $correo)->where('rol', 'docente')->value('id');
                if (! $userId) {
                    $errores[] = "Fila {$n}, columna 'correo': no existe un usuario docente con el correo {$correo} (déjalo vacío si aún no tiene cuenta).";
                }
            }

            $validas[] = [
                'nombre' => $nombre, 'apellido' => $apellido, 'grado' => $grado,
                'ci' => $ci, 'ci_complemento' => $complemento, 'user_id' => $userId,
            ];
        }

        if ($errores) {
            return back()->withErrors(['importacion' => $errores]);
        }

        DB::transaction(function () use ($validas) {
            foreach ($validas as $datos) {
                Docente::create($datos);
            }
        });

        return back()->with('success', 'Se importaron '.count($validas).' docentes correctamente.');
    }

    /** "lic", "Ing." o "doctor" -> el valor oficial de Grados::OPCIONES (null si no se reconoce). */
    private function normalizarGrado(string $texto): ?string
    {
        $clave = LectorTabla::normalizarColumna($texto);
        if ($clave === '') {
            return null;
        }

        foreach (array_keys(Grados::OPCIONES) as $oficial) {
            if (LectorTabla::normalizarColumna($oficial) === $clave) {
                return $oficial;
            }
        }

        return [
            'lic' => 'Licenciado(a)', 'licenciado' => 'Licenciado(a)', 'licenciada' => 'Licenciado(a)',
            'ing' => 'Ingeniero(a)', 'ingeniero' => 'Ingeniero(a)', 'ingeniera' => 'Ingeniero(a)',
            'dr' => 'Doctor(a)', 'dra' => 'Doctor(a)', 'doctor' => 'Doctor(a)', 'doctora' => 'Doctor(a)',
            'mg' => 'Magíster', 'msc' => 'Magíster', 'magister' => 'Magíster',
            'tec' => 'Técnico', 'tecnico' => 'Técnico', 'bach' => 'Bachiller',
        ][$clave] ?? null;
    }

    private function validar(Request $request, ?Docente $actual = null): array
    {
        $request->merge([
            'nombre' => Texto::nombrePropio($request->input('nombre')),
            'apellido' => Texto::nombrePropio($request->input('apellido')),
            'ci_complemento' => strtoupper(trim((string) $request->input('ci_complemento'))) ?: null,
            'user_id' => $request->input('user_id') ?: null,
        ]);

        return $request->validate([
            'nombre' => ['required', 'string', 'max:80', 'regex:'.self::REGEX_ALFABETICO],
            'apellido' => ['required', 'string', 'max:80', 'regex:'.self::REGEX_ALFABETICO],
            'grado' => ['required', Rule::in(array_keys(Grados::OPCIONES))],
            'ci' => [
                'required', 'regex:'.self::REGEX_CARNET,
                Rule::unique('docentes', 'ci')->ignore($actual?->id),
            ],
            'ci_complemento' => ['nullable', 'regex:'.self::REGEX_COMPLEMENTO],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('rol', 'docente')],
        ], [
            'nombre.regex' => 'El nombre solo puede contener letras.',
            'apellido.regex' => 'El apellido solo puede contener letras.',
            'ci.regex' => 'El carnet debe ser numérico, entre 5 y 10 dígitos.',
            'ci.unique' => 'Ya existe un docente con ese CI.',
            'ci_complemento.regex' => 'El complemento admite máximo 2 caracteres (letras y/o números).',
        ]);
    }
}
