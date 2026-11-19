<?php

namespace App\Http\Controllers;

use App\Models\HistorialOperacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistorialOperacionController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $this->validarFiltros($request);
        $registros = $this->consulta($filtros)->paginate(25)->withQueryString();
        $hoy = now()->startOfDay();

        return view('historial-operaciones.index', [
            'registros' => $registros,
            'filtros' => $filtros,
            'totalRegistros' => HistorialOperacion::count(),
            'operacionesHoy' => HistorialOperacion::where('fecha_hora', '>=', $hoy)->count(),
            'errores' => HistorialOperacion::where('estado', 'error')->count(),
            'usuarios' => HistorialOperacion::query()->select('usuario_nombre')->distinct()->orderBy('usuario_nombre')->pluck('usuario_nombre'),
            'acciones' => HistorialOperacion::query()->select('accion')->distinct()->orderBy('accion')->pluck('accion'),
            'examenes' => HistorialOperacion::query()->whereNotNull('examen_id')
                ->select('examen_id', 'examen_nombre')->distinct()->orderBy('examen_nombre')->get(),
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $consulta = $this->consulta($this->validarFiltros($request));
        $zona = config('sciem.zona_horaria');

        return response()->streamDownload(function () use ($consulta, $zona) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Fecha y hora', 'Usuario', 'Rol', 'IP', 'Acción', 'Detalle', 'Examen', 'Registros', 'Estado', 'Error'], ';');

            $consulta->chunk(500, function ($registros) use ($salida, $zona) {
                foreach ($registros as $registro) {
                    fputcsv($salida, [
                        $registro->fecha_hora->copy()->timezone($zona)->format('d/m/Y H:i:s'),
                        $this->valorCsv($registro->usuario_nombre),
                        $this->valorCsv($registro->usuario_rol),
                        $this->valorCsv($registro->ip),
                        $this->valorCsv($registro->accion),
                        $this->valorCsv($registro->detalle),
                        $this->valorCsv($registro->examen_nombre),
                        $registro->registros_afectados,
                        $this->valorCsv($registro->estado),
                        $this->valorCsv($registro->error),
                    ], ';');
                }
            });

            fclose($salida);
        }, 'historial-operaciones.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function valorCsv(?string $valor): ?string
    {
        return $valor !== null && preg_match('/^\s*[=+\-@]/u', $valor) ? "'".$valor : $valor;
    }

    private function validarFiltros(Request $request): array
    {
        return $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'usuario' => ['nullable', 'string', 'max:150'],
            'accion' => ['nullable', 'string', 'max:100'],
            'examen_id' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function consulta(array $filtros): Builder
    {
        return HistorialOperacion::query()
            ->when($filtros['desde'] ?? null, fn (Builder $query, string $desde) => $query->whereDate('fecha_hora', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn (Builder $query, string $hasta) => $query->whereDate('fecha_hora', '<=', $hasta))
            ->when($filtros['usuario'] ?? null, fn (Builder $query, string $usuario) => $query->where('usuario_nombre', $usuario))
            ->when($filtros['accion'] ?? null, fn (Builder $query, string $accion) => $query->where('accion', $accion))
            ->when($filtros['examen_id'] ?? null, fn (Builder $query, string $examenId) => $query->where('examen_id', $examenId))
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id');
    }
}
