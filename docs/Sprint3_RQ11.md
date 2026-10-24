# Sprint 3 · RQ11 — Impedir que un estudiante registre su ingreso más de una vez para el mismo examen

| Tarea del backlog | Dónde quedó |
|---|---|
| Clave única (estudiante_id, examen_id) en la tabla `ingreso` | Ya viene en la migración `2026_10_23_000002_create_ingreso_table` (`ingreso_estudiante_examen_unique`); la migración `2026_10_24_000001` solo se asegura de que exista en bases que ya tenían la tabla |
| Consultar el ingreso previo en verificar e ingreso y responder HTTP 409 con fecha, hora y ambiente del ingreso anterior | `VerificacionController::verificar()` e `ingreso()` → `ingresoPrevio()`, `respuestaYaRegistrado()`, `ambienteDelIngreso()` |
| Controlar solicitudes simultáneas (transacción + captura de clave duplicada) y registrar en `intento_ingreso` el motivo `INGRESO_DUPLICADO` | `IngresoService` (transacción) + `catch (UniqueConstraintViolationException)` + `registrarIntentoRechazado(..., 'INGRESO_DUPLICADO')` |
| Alerta amarilla "El estudiante ya ingresó" con fecha, hora y ambiente anteriores, y botón de ingreso desactivado | `resources/views/verificacion/index.blade.php` |

## Cómo probar
1. `php artisan migrate`.
2. En Verificación, verifica a un estudiante habilitado y presiona **Autorizar ingreso** (queda registrado).
3. Verifícalo otra vez: banner **amarillo** "EL ESTUDIANTE YA INGRESÓ" con fecha, hora y aula del primer ingreso, y el botón **Autorizar ingreso** desactivado.
4. En la base: `SELECT * FROM intento_ingreso ORDER BY id DESC;` → una fila con `motivo = 'INGRESO_DUPLICADO'` y `resultado = 'RECHAZADO'`.
5. Doble clic rápido en **Autorizar ingreso** con otro estudiante habilitado: en `ingreso` queda UNA sola fila; la pantalla muestra el ingreso registrado con su hora y aula.
6. `SELECT estudiante_id, examen_id, COUNT(*) FROM ingreso GROUP BY 1,2 HAVING COUNT(*) > 1;` → sin resultados.
