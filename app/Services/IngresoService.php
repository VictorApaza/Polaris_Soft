<?php

namespace App\Services;

use App\Models\Ambiente;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Ingreso;
use App\Models\TransparenciaIngreso;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Registra el ingreso autorizado de un estudiante: fecha y hora automáticas, puestas por el servidor.
 *
 * Todo ocurre en UNA transacción:
 *   1) se crea la fila de `ingreso` (la hora la fija el modelo con el reloj del servidor, America/La_Paz);
 *   2) se crea la fila de `transparencia_ingreso` (RQ8) con LA MISMA hora.
 * Si cualquiera de las dos falla, no se guarda ninguna y la excepción sube al controlador.
 *
 * Esta clase NO recibe fecha ni hora: lo que mande el cliente no tiene por dónde entrar.
 *
 * Uso:
 *
 *   ['ingreso' => $ingreso, 'transparencia' => $transp] =
 *       app(IngresoService::class)->registrar($estudiante, $examen, $request->user(), 'QR', $request);
 */
class IngresoService
{
    public function __construct(private readonly TransparenciaIngresoService $transparencia)
    {
    }

    /**
     * @param  string        $metodo    CODIGO_UNIV | CI | QR
     * @param  Request|null  $request   Para capturar IP y navegador en transparencia_ingreso (opcional).
     * @param  Ambiente|null $ambiente  Aula donde se controla el ingreso; si no se indica, se usa la del examen.
     * @return array{ingreso: Ingreso, transparencia: TransparenciaIngreso}
     *
     * @throws \Illuminate\Database\UniqueConstraintViolationException si el estudiante ya ingresó a ese examen.
     */
    public function registrar(
        Estudiante $estudiante,
        Examen $examen,
        User $controlador,
        string $metodo,
        ?Request $request = null,
        ?Ambiente $ambiente = null
    ): array {
        $metodo = strtoupper(trim($metodo));

        if (! array_key_exists($metodo, TransparenciaIngreso::METODOS)) {
            throw new InvalidArgumentException(
                'Método de verificación no válido: '.$metodo.' (use CODIGO_UNIV, CI o QR).'
            );
        }

        return DB::transaction(function () use ($estudiante, $examen, $controlador, $metodo, $request, $ambiente) {
            $ambienteId = $ambiente
                ? $ambiente->id
                : Ambiente::where('nombre', (string) $examen->ambiente)->value('id');

            $ingreso = Ingreso::create([
                'estudiante_id' => $estudiante->getKey(),
                'examen_id' => $examen->getKey(),
                'ambiente_id' => $ambienteId,
                'controlador_id' => $controlador->getKey(),
                'metodo_verificacion' => $metodo,
            ]);

            $transparencia = $this->transparencia->registrar(
                $estudiante,
                $examen,
                $controlador,
                $metodo,
                $request,
                $ambiente,
                $ingreso->fecha_hora_ingreso
            );

            return ['ingreso' => $ingreso, 'transparencia' => $transparencia];
        });
    }
}
