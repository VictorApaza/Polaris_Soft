<?php

namespace App\Http\Controllers;

use App\Models\AccesoLog;
use Illuminate\Http\Request;

/** Transparencia de accesos al sistema: quién entró bien y quién falló la contraseña (solo administrador). */
class AccesoLogController extends Controller
{
    public function index(Request $request)
    {
        $resultado = $request->string('resultado')->toString();   // '' | exitosos | fallidos
        $q = trim($request->string('q')->toString());

        $registros = AccesoLog::query()
            ->when($resultado === 'exitosos', fn ($w) => $w->where('exitoso', true))
            ->when($resultado === 'fallidos', fn ($w) => $w->where('exitoso', false))
            ->when($request->filled('tipo'), fn ($w) => $w->where('tipo', $request->tipo))
            ->when($q !== '', fn ($w) => $w->where('identificador', 'like', "%{$q}%"))
            ->orderByDesc('fecha_hora')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $desde = now()->subDay();

        return view('transparencia.accesos', [
            'registros' => $registros,
            'resultado' => $resultado,
            'q' => $q,
            'totalExitosos24h' => AccesoLog::where('exitoso', true)->where('fecha_hora', '>=', $desde)->count(),
            'totalFallidos24h' => AccesoLog::where('exitoso', false)->where('fecha_hora', '>=', $desde)->count(),
        ]);
    }
}
