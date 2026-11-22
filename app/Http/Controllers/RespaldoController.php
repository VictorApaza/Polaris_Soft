<?php

namespace App\Http\Controllers;

use App\Services\RespaldoService;
use Illuminate\Http\Request;
use Throwable;

/** RQ26 (Sprint 5): respaldo (backup) y restauración de la información. Solo el administrador. */
class RespaldoController extends Controller
{
    public function __construct(private readonly RespaldoService $respaldos)
    {
    }

    public function index()
    {
        $lista = $this->respaldos->listar();

        return view('respaldos.index', [
            'respaldos' => $lista,
            'ultimo' => $lista[0] ?? null,
            'bytes' => array_sum(array_column($lista, 'bytes')),
            'hora' => config('sciem.respaldo_hora'),
            'dias' => (int) config('sciem.respaldo_conservar_dias'),
            'conArchivos' => (bool) config('sciem.respaldo_con_archivos'),
        ]);
    }

    /** Crea un respaldo ahora mismo. */
    public function store(Request $request)
    {
        try {
            $r = $this->respaldos->crear('manual', $request->user());
        } catch (Throwable $e) {
            return redirect()->route('respaldos.index')->with('error', $e->getMessage());
        }

        return redirect()->route('respaldos.index')->with('status',
            "Respaldo creado: {$r['archivo']} ({$r['tablas']} tablas, ".number_format($r['filas'], 0, ',', '.')." filas, {$r['archivos']} archivos).");
    }

    public function descargar(string $nombre)
    {
        return response()->download($this->respaldos->rutaAbsoluta($nombre), $nombre.'.zip', ['Cache-Control' => 'private, no-store']);
    }

    /** Comprueba que el archivo del respaldo no esté dañado (no toca la base de datos). */
    public function verificar(string $nombre)
    {
        $r = $this->respaldos->verificar($nombre);

        return redirect()->route('respaldos.index')->with('verificacion', ['nombre' => $nombre] + $r);
    }

    /** Restaura. Pide escribir RESTAURAR porque reemplaza toda la información actual. */
    public function restaurar(Request $request, string $nombre)
    {
        $request->merge(['confirmacion' => strtoupper(trim((string) $request->input('confirmacion')))]);
        $request->validate(['confirmacion' => ['required', 'in:RESTAURAR']], [
            'confirmacion.required' => 'Escribe RESTAURAR para confirmar.',
            'confirmacion.in' => 'Escribe RESTAURAR (en mayúsculas) para confirmar.',
        ]);

        try {
            $r = $this->respaldos->restaurar($nombre, $request->user());
        } catch (Throwable $e) {
            return redirect()->route('respaldos.index')->with('error', 'No se restauró: '.$e->getMessage());
        }

        if (! $r['ok']) {
            return redirect()->route('respaldos.index')->with('error',
                'Se restauró, pero la verificación de integridad encontró diferencias: '.implode(' ', $r['diferencias'])
                ." Puedes volver al estado anterior con el respaldo {$r['previo']}.");
        }

        return redirect()->route('respaldos.index')->with('status',
            "Restauración completa y verificada: {$r['tablas']} tablas y ".number_format($r['filas'], 0, ',', '.')." filas coinciden con el respaldo; {$r['archivos']} archivos recuperados. "
            ."Antes se guardó una copia del estado anterior ({$r['previo']}).");
    }

    public function destroy(string $nombre)
    {
        $this->respaldos->eliminar($nombre);

        return redirect()->route('respaldos.index')->with('status', 'Respaldo eliminado.');
    }
}
