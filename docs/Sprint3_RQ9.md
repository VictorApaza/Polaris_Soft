# Sprint 3 · RQ9 — Comprobar que el estudiante esté habilitado antes de permitir el ingreso

| Tarea del backlog | Dónde quedó |
|---|---|
| Migración de `intento_ingreso` (estudiante, examen, ambiente, controlador, fecha_hora, resultado, motivo) | La tabla ya existía (Sprint 2). Migración `2026_10_23_000001` agrega `ambiente_id` y `resultado` (RECHAZADO) |
| Índice compuesto en `habilitacion (estudiante_id, examen_id)` y relación con `motivo_inhabilitacion` | Ya existía como clave única; la migración lo asegura en bases que no lo tengan. Relación: `Habilitacion::motivo()` |
| POST `/control-ingreso/verificar`: estado ACTIVO + habilitación, devuelve `tipo` (habilitado / no_habilitado / inactivo) y `motivo` | `VerificacionController::verificar()` |
| POST `/control-ingreso/ingreso`: repite la validación y responde HTTP 422 si no corresponde, registrando el intento | `VerificacionController::ingreso()` + `registrarIntentoRechazado()` |
| Vista: HABILITADO (verde) / NO HABILITADO (rojo) con motivo; botón "Registrar ingreso" activo o desactivado | `resources/views/verificacion/index.blade.php` |
| Alerta cuando el estado no es ACTIVO, sin permitir el ingreso | Mismo controlador y vista (banner rojo + botón desactivado) |
| Comparar el ambiente de control con el ambiente asignado y volver a validar antes de registrar | `VerificacionController::verificar()` y `VerificacionController::ingreso()` |

## Cómo probar
1. `php artisan migrate`.
2. En Verificación, elige un examen y confirma que se seleccione su ambiente asignado. Verifica a un estudiante **habilitado**: banner verde, ambiente coincidente y botón "Registrar ingreso" activo.
3. Verifica a uno **inhabilitado**: banner rojo con el motivo y botón desactivado.
4. Pon a un estudiante en estado distinto de ACTIVO: banner rojo "No activo" y botón desactivado.
5. En la tabla `intento_ingreso` deben aparecer los rechazos con `ambiente_id` y `resultado = RECHAZADO`.
6. Cambia el ambiente de control a uno distinto del asignado: debe aparecer "AMBIENTE INCORRECTO", sin botón de ingreso y con la nota RQ21.
7. Prueba el rechazo del servidor aunque se omita la pantalla: `POST /control-ingreso/ingreso` con un ambiente distinto debe devolver 422 y no crear un ingreso.
