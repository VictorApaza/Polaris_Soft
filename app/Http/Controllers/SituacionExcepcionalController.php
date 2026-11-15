<?php

namespace App\Http\Controllers;

use App\Models\Ambiente;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Ingreso;
use App\Models\SituacionExcepcional;
use App\Models\TransparenciaIngreso;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * RQ21: situaciones excepcionales durante un examen.
 *
 *  POST  /control-ingreso/situaciones                        → registra una situación (botón de Verificación)
 *  GET   /control-ingreso/examen/{examen}/situaciones        → lista las del examen (pestaña "Situaciones")
 *  PATCH /control-ingreso/situaciones/{situacion}/revisar    → la marca como REVISADA
 *
 * El usuario que registra es el autenticado y la fecha y hora las pone el servidor (America/La_Paz): nada de eso se
 * recibe desde el navegador. `estado` tampoco: toda situación nace PENDIENTE.
 */
class SituacionExcepcionalController extends Controller
{
    /** Tope de filas por respuesta (igual que Control en vivo). */
    private const LIMITE = 2000;

    /** POST /control-ingreso/situaciones */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'examen_id' => ['required', 'integer', 'exists:examenes,id'],
            'categoria' => ['required', 'string', Rule::in(array_keys(SituacionExcepcional::CATEGORIAS))],
            'detalle' => ['required', 'string', 'min:5', 'max:1000'],
            // El estudiante se indica por su id o, más cómodo en la puerta, por su código universitario o cédula.
            'estudiante_id' => ['nullable', 'integer', 'exists:estudiante,id_estudiante'],
            'estudiante_ref' => ['nullable', 'string', 'max:30'],
            'ambiente_destino_id' => [
                Rule::requiredIf(fn () => $request->input('categoria') === SituacionExcepcional::CAMBIO_AMBIENTE),
                'nullable', 'integer', 'exists:ambientes,id',
            ],
        ], [
            'examen_id.required' => 'Falta el examen.',
            'examen_id.exists' => 'El examen seleccionado no existe.',
            'categoria.required' => 'Elige la categoría de la situación.',
            'categoria.in' => 'La categoría no es válida.',
            'detalle.required' => 'Escribe el detalle de la situación.',
            'detalle.min' => 'El detalle debe tener al menos 5 caracteres.',
            'detalle.max' => 'El detalle no puede pasar de 1000 caracteres.',
            'estudiante_id.exists' => 'El estudiante indicado no existe.',
            'ambiente_destino_id.required' => 'Elige el ambiente al que se cambia al estudiante.',
            'ambiente_destino_id.exists' => 'El ambiente destino no existe.',
        ]);

        $examen = Examen::findOrFail($data['examen_id']);
        if (! $examen->permiteA($request->user())) {
            return response()->json(['mensaje' => 'Ese examen no es de una de tus materias.'], 403);
        }

        $categoria = $data['categoria'];
        $estudiante = $this->resolverEstudiante($data);

        if (! $estudiante && in_array($categoria, SituacionExcepcional::REQUIEREN_ESTUDIANTE, true)) {
            throw ValidationException::withMessages([
                'estudiante_ref' => 'Indica el estudiante (código universitario o cédula) para esta categoría.',
            ]);
        }

        // Origen y destino solo existen en un cambio de ambiente; en las demás categorías se ignoran.
        $origenId = null;
        $destinoId = null;
        if ($categoria === SituacionExcepcional::CAMBIO_AMBIENTE) {
            $destinoId = (int) $data['ambiente_destino_id'];
            $origenId = $this->ambienteActual($estudiante, $examen);

            if ($origenId === $destinoId) {
                throw ValidationException::withMessages([
                    'ambiente_destino_id' => 'El ambiente destino debe ser distinto del ambiente actual del estudiante ('
                        .Ambiente::find($origenId)?->nombre.').',
                ]);
            }
        }

        $situacion = SituacionExcepcional::create([
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante?->id_estudiante,
            'categoria' => $categoria,
            'detalle' => trim($data['detalle']),
            'ambiente_origen_id' => $origenId,
            'ambiente_destino_id' => $destinoId,
            'registrado_por' => $request->user()->id,
        ]);

        $situacion->refresh()->load($this->relaciones());

        return response()->json([
            'mensaje' => 'Situación excepcional registrada.',
            'situacion' => $this->aArreglo($situacion),
        ], 201);
    }

    /** GET /control-ingreso/examen/{examen}/situaciones[?estado=PENDIENTE|REVISADA][&categoria=…] */
    public function porExamen(Request $request, Examen $examen): JsonResponse
    {
        abort_unless($examen->permiteA($request->user()), 403, 'Ese examen no es de una de tus materias.');

        $request->validate([
            'estado' => ['nullable', Rule::in([SituacionExcepcional::PENDIENTE, SituacionExcepcional::REVISADA])],
            'categoria' => ['nullable', Rule::in(array_keys(SituacionExcepcional::CATEGORIAS))],
        ]);

        $situaciones = SituacionExcepcional::with($this->relaciones())
            ->where('examen_id', $examen->id)
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria', $request->categoria))
            ->orderByDesc('fecha_hora')->orderByDesc('id')
            ->limit(self::LIMITE)
            ->get();

        return response()->json([
            'examen_id' => $examen->id,
            'total' => $situaciones->count(),
            'pendientes' => SituacionExcepcional::where('examen_id', $examen->id)->where('estado', SituacionExcepcional::PENDIENTE)->count(),
            'hora_servidor' => now()->timezone(TransparenciaIngreso::zonaHoraria())->format('d/m/Y H:i:s'),
            'situaciones' => $situaciones->map(fn ($s) => $this->aArreglo($s))->values(),
        ]);
    }

    /** PATCH /control-ingreso/situaciones/{situacion}/revisar */
    public function revisar(Request $request, SituacionExcepcional $situacion): JsonResponse
    {
        abort_unless($situacion->examen && $situacion->examen->permiteA($request->user()), 403, 'Esa situación no es de un examen de tus materias.');

        $cambio = $situacion->marcarRevisada($request->user());
        $situacion->load($this->relaciones());

        return response()->json([
            'mensaje' => $cambio ? 'Situación marcada como revisada.' : 'La situación ya estaba revisada.',
            'ya_estaba_revisada' => ! $cambio,
            'situacion' => $this->aArreglo($situacion),
        ]);
    }

    /**
     * Estudiante al que se refiere la situación, o null si no se indicó ninguno.
     * Acepta `estudiante_id`, o `estudiante_ref` = código universitario (9 dígitos) o cédula.
     */
    private function resolverEstudiante(array $data): ?Estudiante
    {
        if (! empty($data['estudiante_id'])) {
            return Estudiante::find($data['estudiante_id']);
        }

        $ref = trim((string) ($data['estudiante_ref'] ?? ''));
        if ($ref === '') {
            return null;
        }

        $porCodigo = Estudiante::where('codigo_universitario', $ref)->first();
        if ($porCodigo) {
            return $porCodigo;
        }

        $porCi = Estudiante::where('documento_identidad', $ref)->get();
        if ($porCi->count() === 1) {
            return $porCi->first();
        }

        throw ValidationException::withMessages([
            'estudiante_ref' => $porCi->isEmpty()
                ? 'No hay ningún estudiante con ese código universitario o cédula.'
                : 'Esa cédula corresponde a más de un estudiante: usa el código universitario.',
        ]);
    }

    /** Ambiente en el que está hoy el estudiante: el de su ingreso, o si no ingresó, el asignado al examen. */
    private function ambienteActual(?Estudiante $estudiante, Examen $examen): ?int
    {
        $delIngreso = $estudiante
            ? Ingreso::where('estudiante_id', $estudiante->id_estudiante)->where('examen_id', $examen->id)->value('ambiente_id')
            : null;

        $id = $delIngreso ?: Ambiente::where('nombre', (string) $examen->ambiente)->value('id');

        return $id ? (int) $id : null;
    }

    private function relaciones(): array
    {
        return ['estudiante', 'ambienteOrigen', 'ambienteDestino', 'registrador', 'revisor'];
    }

    private function aArreglo(SituacionExcepcional $s): array
    {
        $e = $s->estudiante;

        return [
            'id' => $s->id,
            'examen_id' => $s->examen_id,
            'categoria' => $s->categoria,
            'categoria_etiqueta' => $s->categoria_etiqueta,
            'estudiante_id' => $e?->id_estudiante,
            'codigo' => $e?->codigo_universitario,
            'ci' => $e ? $e->documento_identidad.($e->ci_complemento ? '-'.$e->ci_complemento : '') : null,
            'nombre' => $e ? trim($e->nombres.' '.$e->apellidos) : null,
            'detalle' => $s->detalle,
            'ambiente_origen' => $s->ambienteOrigen?->nombre,
            'ambiente_destino' => $s->ambienteDestino?->nombre,
            'registrado_por' => $this->nombreUsuario($s->registrador),
            'fecha_hora' => $s->fecha_hora->format('Y-m-d H:i:s'),
            'fecha_hora_local' => $s->fecha_hora_local,
            'estado' => $s->estado,
            'revisada_por' => $s->revisada_por ? $this->nombreUsuario($s->revisor) : null,
            'revisada_at_local' => $s->revisada_at_local,
        ];
    }

    private function nombreUsuario(?User $usuario): string
    {
        if (! $usuario) {
            return '—';
        }

        return trim(($usuario->nombre ?? '').' '.($usuario->apellido ?? '')) ?: (string) $usuario->name;
    }
}
