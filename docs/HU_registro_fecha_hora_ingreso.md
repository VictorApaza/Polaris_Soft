# H.U. · Registrar automáticamente la fecha y hora de ingreso del estudiante

Depende de RQ9 (verificar habilitación y botón "Autorizar ingreso") y de RQ8 (`transparencia_ingreso`).

| Tarea del backlog | Dónde quedó |
|---|---|
| Migración de la tabla `ingreso` (estudiante_id, examen_id, ambiente_id, controlador_id, metodo_verificacion, fecha_hora_ingreso) con índices por examen y estudiante | `database/migrations/2026_10_23_000002_create_ingreso_table.php` + `app/Models/Ingreso.php` |
| Hora desde el servidor con zona `America/La_Paz` (aplicación y MySQL), ignorando lo que mande el cliente | `config/app.php` (`APP_TIMEZONE`), `config/database.php` (`DB_TIMEZONE = -04:00`), `Ingreso::booted()` fija la hora al crear |
| Registrar dentro de una transacción al autorizar, sin edición posterior, y devolver la hora (coherente con `transparencia_ingreso`, RQ8) | `app/Services/IngresoService.php` (usado por `VerificacionController::ingreso()` y `TransparenciaIngresoController::registrar()`) |
| Mostrar la hora (dd/mm/aaaa HH:mm:ss) al controlador y, si falla el guardado, mostrar el error con opción de reintentar | `resources/views/verificacion/index.blade.php` (botón **Autorizar ingreso** → banner "INGRESO REGISTRADO"; error con botón **Reintentar**) |

## Cómo funciona

1. El controlador verifica al estudiante (verde = habilitado) y presiona **Autorizar ingreso** (`POST /verificacion/ingreso`).
2. `IngresoService::registrar()` abre **una transacción** y guarda:
   - la fila de `ingreso` (la hora la pone el modelo con el reloj del servidor, sin segundos fraccionarios), y
   - la fila de `transparencia_ingreso` con **exactamente la misma hora**.
   Si cualquiera falla, no queda ninguna.
3. La respuesta `201` trae `registro.fecha_hora_local` (`23/10/2026 17:58:12`) y `registro.fecha_hora` (ISO 8601 con `-04:00`).
4. La pantalla muestra la hora como confirmación.

El cliente **no envía** fecha ni hora. Si manda `fecha_hora_ingreso`, `fecha_hora`, etc., se descartan (`fecha_hora_ingreso` ni siquiera es asignable en masa).

## Inmutabilidad

- `Ingreso` lanza `LogicException` en `update` y `delete`.
- En MySQL, triggers `ingreso_no_update` / `ingreso_no_delete` rechazan cambios hechos directo en la base.
- Índice único `(estudiante_id, examen_id)`: un estudiante solo ingresa una vez por examen (RQ11).

## Respuestas de `POST /verificacion/ingreso`

| HTTP | Cuándo | Qué hace la pantalla |
|---|---|---|
| 201 | Guardado | Banner verde "INGRESO REGISTRADO" con la hora |
| 409 | Ya estaba registrado (doble clic, o reintento cuando se perdió la respuesta) | Banner ámbar "INGRESO YA REGISTRADO" con la hora original; no duplica |
| 422 | No habilitado / no activo | Mensaje de error, **sin** Reintentar (reintentar no cambia nada) |
| 500 / sin red | Falló el guardado (no se guardó nada) | Mensaje de error + botón **Reintentar** |

## Zona horaria: qué cambia

`config/app.php` pasó de `'UTC'` a `America/La_Paz`, y la sesión MySQL usa `-04:00` (Bolivia no tiene horario de verano; se usa el desfase numérico para no depender de las tablas de zonas horarias de MySQL).

- Las filas **nuevas** (`ingreso`, `transparencia_ingreso`, verificaciones, incidencias, accesos…) se guardan en hora de Bolivia.
- Las filas **anteriores** se guardaron en UTC, así que pueden verse con 4 horas de diferencia respecto a la hora real de Bolivia. En desarrollo se puede ignorar o hacer `php artisan migrate:fresh --seed`; en una base con datos reales habría que convertirlas a mano.
- Variables de `.env` (ver `.env.example`): `APP_TIMEZONE=America/La_Paz` y `DB_TIMEZONE=-04:00`. Si cambias una, cambia la otra.

## Cómo probar

1. `php artisan migrate`.
2. En **Verificación**, elige un examen y verifica a un estudiante habilitado → **Autorizar ingreso**.
3. Debe aparecer "INGRESO REGISTRADO" con la hora `dd/mm/aaaa HH:mm:ss` en hora de Bolivia.
4. En la base: `SELECT * FROM ingreso;` y `SELECT * FROM transparencia_ingreso;` → misma `fecha_hora_ingreso`.
5. Intenta editarlo: `UPDATE ingreso SET metodo_verificacion='CI';` → error del trigger (MySQL).
6. Prueba el error: apaga la base (o desconecta la red) y presiona **Autorizar ingreso** → mensaje de error y botón **Reintentar**; al volver la conexión, **Reintentar** guarda una sola vez.
7. Prueba que se ignora la hora del cliente: `POST /verificacion/ingreso` con `"fecha_hora_ingreso": "2000-01-01 00:00:00"` → se guarda la hora del servidor.
