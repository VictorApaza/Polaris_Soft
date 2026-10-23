# Sprint 3 · RQ9 — Comprobar que el estudiante esté habilitado antes de permitir el ingreso

| Tarea del backlog | Dónde quedó |
|---|---|
| Migración de `intento_ingreso` (estudiante, examen, ambiente, controlador, fecha_hora, resultado, motivo) | La tabla ya existía (Sprint 2). Migración `2026_10_23_000001` agrega `ambiente_id` y `resultado` (RECHAZADO) |
| Índice compuesto en `habilitacion (estudiante_id, examen_id)` y relación con `motivo_inhabilitacion` | Ya existía como clave única; la migración lo asegura en bases que no lo tengan. Relación: `Habilitacion::motivo()` |
| POST `/control-ingreso/verificar`: estado ACTIVO + habilitación, devuelve `tipo` (habilitado / no_habilitado / inactivo) y `motivo` | `VerificacionController::verificar()` |
| POST `/control-ingreso/ingreso`: repite la validación y responde HTTP 422 si no corresponde, registrando el intento | `VerificacionController::ingreso()` + `registrarIntentoRechazado()` |
| Vista: HABILITADO (verde) / NO HABILITADO (rojo) con motivo; botón "Autorizar ingreso" activo o desactivado | `resources/views/verificacion/index.blade.php` |
| Alerta cuando el estado no es ACTIVO, sin permitir el ingreso | Mismo controlador y vista (banner rojo + botón desactivado) |

## Cómo probar
1. `php artisan migrate`.
2. En Verificación, elige un examen y verifica a un estudiante **habilitado**: banner verde y botón "Autorizar ingreso" activo.
3. Verifica a uno **inhabilitado**: banner rojo con el motivo y botón desactivado.
4. Pon a un estudiante en estado distinto de ACTIVO: banner rojo "No activo" y botón desactivado.
5. En la tabla `intento_ingreso` deben aparecer los rechazos con `ambiente_id` y `resultado = RECHAZADO`.
6. Prueba el rechazo del servidor aunque el botón no esté: `POST /control-ingreso/ingreso` con un estudiante no habilitado debe devolver 422.
