# Sprint 3 · RQ12 — Registrar la intención de ingreso de un estudiante a un examen que no le corresponde

**Cuándo ocurre:** el estudiante no tiene ninguna habilitación para ese examen (ni habilitado ni inhabilitado) **y** no está inscrito en la asignatura/materia del examen (mismo criterio que `Habilitacion::candidatosIds()`).
Si sí es de la materia pero nadie lo habilitó, sigue siendo el caso de RQ9 ("no tiene habilitación registrada"): no cambió.

| Tarea del backlog | Dónde quedó |
|---|---|
| Detectar que el examen no le corresponde al estudiante | `app/Services/ExamenNoCorrespondeService.php` → `noLeCorresponde()` (archivo nuevo) |
| Registrar el intento (estudiante, examen, aula, controlador, método, fecha/hora) con motivo `EXAMEN_NO_CORRESPONDE` | `VerificacionController::verificar()` → `registrarIntentoRechazado(...)` en la tabla `intento_ingreso`; la verificación queda en `verificacion` con `resultado = EXAMEN_NO_CORRESPONDE`. No hay migración nueva |
| Repetir la validación en el servidor aunque se salte la pantalla (HTTP 422 + intento registrado) | `VerificacionController::ingreso()` |
| Alerta inmediata al personal de control | `resources/views/verificacion/index.blade.php`: banner rojo parpadeante "EXAMEN NO CORRESPONDE", alarma sonora y vibración (celular/tablet), "Intento n.º N", y el examen que **sí** le corresponde (nombre, fecha, hora, aula). El botón "Autorizar ingreso" queda desactivado |
| Base para el reporte de RQ19 | `SELECT ... FROM intento_ingreso WHERE motivo = 'EXAMEN_NO_CORRESPONDE'` (constante `ExamenNoCorrespondeService::MOTIVO`) |

Respuesta JSON nueva (HTTP 422): `tipo = no_corresponde`, `mensaje`, `motivo`, `alerta`, `hora_intento`, `numero_intento`, `examenes_asignados[]` y los datos del estudiante.

## Qué NO se tocó
Migraciones, modelos, rutas, `IngresoService`, reportes, incidencias ni las pantallas de los demás módulos. En `VerificacionController` solo se agregó el constructor con el servicio y los dos bloques de RQ12; los casos de RQ9 (no habilitado / inactivo) y RQ11 (ya ingresó) siguen igual.

## Cómo probar
1. `php artisan migrate` (no hay migraciones nuevas) y `php artisan migrate:fresh --seed` si quieres datos de ejemplo.
2. Crea dos exámenes de **asignaturas distintas**: A y B. Un estudiante inscrito solo en B y habilitado en B.
3. En Verificación elige el examen **A** y verifica a ese estudiante (por código, cédula o QR): banner rojo **EXAMEN NO CORRESPONDE**, sonido, "Intento n.º 1", y abajo "Su examen: B · fecha · hora · aula". "Autorizar ingreso" desactivado.
4. Verifícalo otra vez: "Intento n.º 2".
5. En la base:
   `SELECT estudiante_id, examen_id, tipo, resultado, motivo, fecha_hora FROM intento_ingreso WHERE motivo = 'EXAMEN_NO_CORRESPONDE' ORDER BY id DESC;`
   `SELECT resultado FROM verificacion ORDER BY id DESC LIMIT 2;` → `EXAMEN_NO_CORRESPONDE`.
6. Servidor: `POST /verificacion/ingreso` con ese estudiante y el examen A → 422 `no_corresponde` y no se crea fila en `ingreso`.
7. Verifica a un estudiante de la materia **sin** habilitación → sigue saliendo "NO HABILITADO" (RQ9).
8. Prueba automática: `php artisan test --filter=Rq12` (usa transacciones: no borra ni deja datos en la base).
