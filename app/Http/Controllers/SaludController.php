<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * GET /salud — comprobación de disponibilidad para monitoreo (requisito: el sistema debe estar disponible durante
 * todo el periodo de ingreso). Es pública y NO revela datos: solo dice si la aplicación y la base de datos responden.
 * Responde 200 si todo está bien y 503 si la base de datos no responde, para que una herramienta de monitoreo
 * (UptimeRobot, Zabbix, un cron con curl, etc.) avise cuando algo falle.
 */
class SaludController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $baseDatos = true;
        $ms = null;
        try {
            $ini = microtime(true);
            DB::select('select 1');
            $ms = round((microtime(true) - $ini) * 1000, 1);
        } catch (Throwable) {
            $baseDatos = false;
        }

        return response()->json([
            'estado' => $baseDatos ? 'ok' : 'error',
            'base_datos' => $baseDatos ? 'ok' : 'sin_conexion',
            'base_datos_ms' => $ms,
            'hora_servidor' => now()->toIso8601String(),
        ], $baseDatos ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
