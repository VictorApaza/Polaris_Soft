<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un intento de ingresar al sistema (personal o estudiante), exitoso o fallido. Nunca guarda contraseñas. */
class AccesoLog extends Model
{
    protected $table = 'acceso_log';

    public $timestamps = false;

    public const RESULTADOS = [
        'OK' => 'Ingreso correcto',
        'NO_EXISTE' => 'Usuario no existe',
        'INACTIVO' => 'Cuenta inactiva',
        'CONTRASENA_INCORRECTA' => 'Contraseña incorrecta',
        'BLOQUEADO' => 'Bloqueado por muchos intentos',
        'DATOS_INCORRECTOS' => 'Datos incorrectos',
    ];

    protected $fillable = [
        'tipo', 'identificador', 'usuario_id', 'exitoso', 'resultado', 'ip', 'user_agent', 'fecha_hora',
    ];

    protected $casts = ['exitoso' => 'boolean', 'fecha_hora' => 'datetime'];

    /** Anota un intento sin romper nunca el inicio de sesión si la bitácora fallara. */
    public static function anotar(string $tipo, string $identificador, bool $exitoso, string $resultado, ?int $usuarioId = null): void
    {
        try {
            $r = request();
            static::create([
                'tipo' => $tipo,
                'identificador' => mb_substr($identificador, 0, 150),
                'usuario_id' => $usuarioId,
                'exitoso' => $exitoso,
                'resultado' => $resultado,
                'ip' => $r?->ip(),
                'user_agent' => mb_substr((string) $r?->userAgent(), 0, 255),
                'fecha_hora' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
