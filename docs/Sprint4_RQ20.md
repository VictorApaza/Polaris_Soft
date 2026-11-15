# Sprint 4 · RQ20 — Registrar estudiantes expulsados del examen, indicando motivo y hora

Un controlador (o administrador / docente del examen) puede **expulsar a un estudiante que ya ingresó**, con un motivo obligatorio. Queda guardada la hora (la pone el servidor, zona America/La_Paz) y el usuario que lo registró; el estudiante **no puede volver a entrar** a ese examen.

| Tarea del backlog | Dónde quedó |
|---|---|
| Tabla `expulsion` (examen_id, estudiante_id, ambiente_id, motivo, registrado_por, fecha_hora) con clave única (examen_id, estudiante_id) | `database/migrations/2026_11_09_000001_rq20_create_expulsion_table.php` · modelo `app/Models/Expulsion.php` |
| `POST /control-ingreso/expulsiones` con motivo obligatorio, valida que el estudiante tenga ingreso en el examen; hora del servidor y usuario autenticado | `app/Http/Controllers/ExpulsionController.php` → `store()` (ruta `ingreso.expulsar`) |
| Responder `tipo = expulsado` (HTTP 409) en `POST /control-ingreso/verificar` (y en `/control-ingreso/ingreso`) con el motivo y la hora, para impedir el reingreso | `app/Http/Controllers/VerificacionController.php` (se evalúa **antes** de "ya ingresó"); banner rojo "ESTUDIANTE EXPULSADO" en `resources/views/verificacion/index.blade.php` |
| Botón "Expulsar" en la lista "Ingresaron" con modal de motivo obligatorio y marca "EXPULSADO" con hora y motivo | `resources/views/control/en_vivo.blade.php` + modal `resources/views/control/_modal_expulsion.blade.php` |
| Pestaña "Expulsados" (estudiante, motivo, hora y usuario que registró) | `GET /control-ingreso/examen/{examen}/expulsados` (ruta `ingreso.expulsados`) + pestaña en `en_vivo.blade.php` |

## Reglas
- **Motivo obligatorio**: entre 5 y 500 caracteres. El botón "Confirmar" del modal queda desactivado hasta escribirlo.
- **Solo se expulsa a quien ya ingresó** (tiene fila en `ingreso` para ese examen) → si no, HTTP 422 `sin_ingreso`.
- **Una sola expulsión por estudiante y examen** → si ya estaba expulsado, HTTP 422 `ya_expulsado`.
- **La hora y el usuario no se reciben del navegador**: `fecha_hora` y `registrado_por` se ignoran si llegan en la petición.
- **Quién puede**: los mismos roles de Control en vivo (administrador, control y docente), y el docente solo en exámenes de sus materias (`Examen::permiteA`).
- **Reingreso**: la verificación y el registro de ingreso responden HTTP 409 `expulsado`, y el intento queda en `intento_ingreso` con motivo `ESTUDIANTE_EXPULSADO` y en `verificacion` con resultado `EXPULSADO`.
- Control en vivo ya contaba y marcaba a los expulsados cuando existía la tabla (RQ14): el contador "Expulsados" y la marca en "Ingresaron" funcionan sin tocar su controlador.

## Qué NO se tocó
`ControlEnVivoController`, `IngresoService`, `IngresoController`, modelos y migraciones de otros RQ, reportes ni la pestaña "Situaciones" (RQ21). En `VerificacionController` solo se agregó el `use` y dos bloques; en `en_vivo.blade.php` solo lo imprescindible (activar la pestaña, el botón y su lógica); en `verificacion/index.blade.php` solo el banner `expulsado`.

## Cómo probar
1. `php artisan migrate` (crea la tabla `expulsion`).
2. Entra como control o administrador. Debe haber un examen con al menos un estudiante que ya ingresó (verifica y registra su ingreso en **Verificación**).
3. **Control en vivo** → pestaña **Ingresaron** → botón **Expulsar** en la fila del estudiante. El botón "Confirmar" sigue desactivado hasta escribir el motivo. Confirma.
4. La fila pasa a **EXPULSADO** (con la hora y el motivo debajo), desaparece el botón, el contador "Expulsados" sube a 1 y la pestaña **Expulsados** lo muestra con el usuario que lo registró.
5. En **Verificación** vuelve a verificar a ese estudiante: banner rojo **ESTUDIANTE EXPULSADO** con el motivo; el botón de ingreso queda desactivado.
6. Prueba que otro estudiante que ingresó y no fue expulsado sigue mostrando "El estudiante ya ingresó" (RQ11).
7. En la base:
   `SELECT estudiante_id, examen_id, motivo, registrado_por, fecha_hora FROM expulsion;`
   `SELECT tipo, resultado, motivo FROM intento_ingreso WHERE motivo = 'ESTUDIANTE_EXPULSADO';`
8. Prueba automática: `php artisan test --filter=Rq20` (usa transacciones: no deja datos en tu base).
