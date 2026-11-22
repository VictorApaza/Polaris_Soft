<?php

namespace App\Services;

use App\Models\Examen;
use App\Models\HistorialOperacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Throwable;

class HistorialOperacionService
{
    private const ACCIONES = [
        'estudiantes.store' => 'Registrar estudiante',
        'estudiantes.update' => 'Modificar estudiante',
        'estudiantes.destroy' => 'Eliminar estudiante',
        'estudiantes.importar' => 'Importación de estudiantes',
        'estudiantes.password.restablecer' => 'Restablecer contraseña',
        'docentes.store' => 'Registrar docente',
        'docentes.update' => 'Modificar docente',
        'docentes.destroy' => 'Eliminar docente',
        'docentes.importar' => 'Importación de docentes',
        'usuarios.store' => 'Registrar usuario',
        'usuarios.update' => 'Modificar usuario',
        'usuarios.estado' => 'Cambiar estado de usuario',
        'ambientes.store' => 'Registrar ambiente',
        'ambientes.update' => 'Modificar ambiente',
        'ambientes.destroy' => 'Eliminar ambiente',
        'materias.store' => 'Registrar materia',
        'materias.update' => 'Modificar materia',
        'materias.destroy' => 'Eliminar materia',
        'grupos.store' => 'Registrar grupo',
        'grupos.update' => 'Modificar grupo',
        'grupos.destroy' => 'Eliminar grupo',
        'examenes.store' => 'Crear examen',
        'examenes.update' => 'Modificar examen',
        'examenes.destroy' => 'Eliminar examen',
        'habilitaciones.masiva' => 'Habilitación masiva',
        'habilitaciones.inhabilitar' => 'Inhabilitar estudiante',
        'habilitaciones.quitar' => 'Retirar habilitación',
        'verificacion.verificar' => 'Verificar ingreso',
        'verificar.codigo' => 'Verificar ingreso',
        'verificar.ci' => 'Verificar ingreso',
        'verificar.qr' => 'Verificar ingreso',
        'ingreso.verificar' => 'Verificar ingreso',
        'ingreso.ingreso' => 'Registrar ingreso',
        'ingreso.expulsar' => 'Expulsión',
        'ingreso.situaciones.store' => 'Situación excepcional',
        'ingreso.situaciones.revisar' => 'Revisar situación excepcional',
        'incidencias.store' => 'Registrar incidencia',
        'incidencias.revisar' => 'Revisar incidencia',
        'transparencia.registrar' => 'Autorizar ingreso',
        'fotos.individual' => 'Cargar foto individual',
        'fotos.zip' => 'Carga masiva de fotos',
        'fotos.aprobar' => 'Aprobar foto',
        'fotos.observar' => 'Observar foto',
        'respaldos.store' => 'Crear respaldo',
        'respaldos.verificar' => 'Verificar respaldo',
        'respaldos.restaurar' => 'Restaurar respaldo',
        'respaldos.destroy' => 'Eliminar respaldo',
    ];

    private const CAMPOS_DETALLE = [
        'nombre' => 'Nombre',
        'nombres' => 'Nombres',
        'apellido' => 'Apellido',
        'apellidos' => 'Apellidos',
        'name' => 'Nombre',
        'email' => 'Correo',
        'codigo_universitario' => 'Código',
        'codigo' => 'Código',
        'estado' => 'Estado',
        'rol' => 'Rol',
        'tipo' => 'Tipo',
        'fecha' => 'Fecha',
        'hora_inicio' => 'Hora',
        'ambiente' => 'Ambiente',
        'capacidad' => 'Capacidad',
        'materia_id' => 'Materia',
        'grupo_id' => 'Grupo',
        'resultado' => 'Resultado',
        'motivo' => 'Motivo',
        'id_estudiante' => 'Estudiante ID',
        'estudiante_id' => 'Estudiante ID',
    ];

    public function registrar(
        Request $request,
        User $usuario,
        Route $ruta,
        bool $rechazo,
        ?Throwable $exception = null,
        ?int $codigoHttp = null,
        ?string $tipoRespuesta = null,
        bool $rechazoEsperado = false
    ): void {
        try {
            [$examenId, $examenNombre] = $this->datosDelExamen($request, $ruta);
            $mensaje = $this->mensajeDeSesion($request);
            $error = $rechazo
                ? ($exception
                    ? 'Error interno: '.class_basename($exception)
                    : ($codigoHttp && $codigoHttp >= 400
                        ? "La operación terminó con HTTP {$codigoHttp}."
                        : 'Operación rechazada por validación o reglas del sistema.'))
                : null;

            HistorialOperacion::create([
                'usuario_id' => $usuario->getKey(),
                'usuario_nombre' => Str::limit((string) $usuario->name, 150, ''),
                'usuario_rol' => (string) $usuario->rol,
                'ip' => Str::limit((string) $request->ip(), 45, ''),
                'accion' => $rechazoEsperado ? 'Intento rechazado' : ($tipoRespuesta === 'habilitado' ? 'Verificación de ingreso' : $this->accion($ruta, $request)),
                'detalle' => $this->detalle($request, $ruta, $examenNombre, $mensaje, $tipoRespuesta),
                'examen_id' => $examenId,
                'examen_nombre' => $examenNombre,
                'registros_afectados' => $this->cantidadAfectada($request, $rechazo, $mensaje),
                'estado' => $rechazo ? 'error' : 'correcto',
                'error' => $error,
                'fecha_hora' => now(),
            ]);
        } catch (Throwable $loggingError) {
            report($loggingError);
        }
    }

    private function accion(Route $ruta, Request $request): string
    {
        $nombre = $ruta->getName();
        if ($nombre && isset(self::ACCIONES[$nombre])) {
            return self::ACCIONES[$nombre];
        }

        $identificador = $nombre ?: $ruta->uri();
        if (Str::contains($identificador, 'masiva')) {
            return 'Operación masiva';
        }
        if (Str::contains($identificador, 'situaciones')) {
            return 'Situación excepcional';
        }
        if (Str::contains($identificador, 'expulsion')) {
            return 'Expulsión';
        }

        $verbo = match ($request->method()) {
            'POST' => 'Registrar',
            'PUT', 'PATCH' => 'Modificar',
            'DELETE' => 'Eliminar',
            default => 'Operación',
        };

        return Str::limit($verbo.' '.Str::headline(str_replace('.', ' ', $identificador)), 100, '');
    }

    private function detalle(Request $request, Route $ruta, ?string $examenNombre, ?string $mensaje, ?string $tipoRespuesta): string
    {
        $partes = [];

        foreach ($ruta->parameters() as $nombre => $valor) {
            if ($valor instanceof Model && ! $valor instanceof Examen) {
                $partes[] = Str::headline($nombre).' '.$this->etiquetaModelo($valor);
            } elseif (is_scalar($valor) && is_numeric($valor) && $nombre !== 'examen') {
                $partes[] = Str::headline($nombre).' #'.$valor;
            }
        }

        foreach (self::CAMPOS_DETALLE as $campo => $etiqueta) {
            $valor = $request->input($campo);
            if (is_scalar($valor) && trim((string) $valor) !== '') {
                $partes[] = $etiqueta.': '.Str::limit(trim((string) $valor), 80, '');
            }
        }

        if ($examenNombre) {
            $partes[] = 'Examen: '.$examenNombre;
        }

        if ($tipoRespuesta) {
            $partes[] = 'Resultado: '.Str::headline(str_replace('_', ' ', $tipoRespuesta));
        }

        if ($mensaje && $this->cantidadDelMensaje($mensaje) !== null) {
            $partes[] = Str::limit($mensaje, 180, '');
        }

        return Str::limit(implode('; ', array_unique($partes)) ?: 'Operación registrada', 1000, '');
    }

    private function datosDelExamen(Request $request, Route $ruta): array
    {
        $examen = $ruta->parameter('examen');

        if ($examen instanceof Examen) {
            return [$examen->getKey(), $examen->nombre];
        }

        $id = is_numeric($examen) ? (int) $examen : ($request->input('examen_id') ?? $request->input('id_examen'));
        if (! is_numeric($id)) {
            return [null, null];
        }

        $registro = Examen::query()->with(['materia', 'asignatura'])->find((int) $id);

        return $registro ? [$registro->getKey(), $registro->nombre] : [(int) $id, null];
    }

    private function etiquetaModelo(Model $modelo): string
    {
        foreach (['nombre_completo', 'nombre', 'name', 'codigo_universitario', 'codigo'] as $atributo) {
            $valor = $modelo->getAttribute($atributo);
            if (is_scalar($valor) && trim((string) $valor) !== '') {
                return '#'.$modelo->getKey().' ('.Str::limit(trim((string) $valor), 100, '').')';
            }
        }

        return '#'.$modelo->getKey();
    }

    private function mensajeDeSesion(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        foreach (['status', 'success', 'error'] as $clave) {
            $mensaje = $request->session()->get($clave);
            if (is_string($mensaje) && trim($mensaje) !== '') {
                return trim($mensaje);
            }
        }

        return null;
    }

    private function cantidadAfectada(Request $request, bool $rechazo, ?string $mensaje): int
    {
        if ($rechazo) {
            return 0;
        }

        if ($mensaje && ($cantidad = $this->cantidadDelMensaje($mensaje)) !== null) {
            return $cantidad;
        }

        foreach (['estudiantes', 'usuarios', 'ids', 'registros', 'codigos'] as $campo) {
            $valores = $request->input($campo);
            if (is_array($valores)) {
                return count($valores);
            }
        }

        return 1;
    }

    private function cantidadDelMensaje(string $mensaje): ?int
    {
        if (preg_match('/\b(\d+)\s+(?:estudiantes|docentes|registros|usuarios|habilitaciones|fotos)\b/iu', $mensaje, $coincidencia)) {
            return (int) $coincidencia[1];
        }

        return null;
    }
}
