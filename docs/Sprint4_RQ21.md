# Sprint 4 · RQ21 — Registrar situaciones excepcionales (problemas de identificación, cambios de ambiente, etc.)

Un controlador (o administrador / docente del examen) puede **registrar una situación excepcional** durante el examen: problema de identificación, cambio de ambiente, estudiante no habilitado u otra. Cada situación guarda categoría, detalle, estudiante (si aplica), ambiente origen/destino, quién la registró y la hora (la pone el servidor, zona America/La_Paz). En **Control en vivo → Situaciones** se listan y se pueden marcar como **REVISADA**.

| Tarea del backlog | Dónde quedó |
|---|---|
| Migración de la tabla `situacion_excepcional` (examen_id, estudiante_id, categoria, detalle, ambiente_origen_id, ambiente_destino_id, registrado_por, fecha_hora, estado) | `database/migrations/2026_11_11_000001_rq21_create_situacion_excepcional_table.php` · modelo `app/Models/SituacionExcepcional.php` |
| Categorías `PROBLEMA_IDENTIFICACION`, `CAMBIO_AMBIENTE`, `ESTUDIANTE_NO_HABILITADO`, `OTRA`; detalle libre obligatorio; ambiente destino obligatorio en cambios de ambiente | constantes del modelo + validación en `app/Http/Controllers/SituacionExcepcionalController.php` → `store()` |
| `POST /control-ingreso/situaciones` (usuario y fecha_hora del servidor) | ruta `ingreso.situaciones.store` en `routes/web.php` |
| `GET /control-ingreso/examen/{examen}/situaciones` | ruta `ingreso.situaciones` → `porExamen()` |
| Botón "Registrar situación excepcional" con modal (categoría, detalle, ambiente destino) en Verificación | `resources/views/verificacion/index.blade.php` (botón arriba a la derecha) + modal `resources/views/verificacion/_modal_situacion.blade.php` |
| Pestaña "Situaciones" (hora, categoría, estudiante, detalle, usuario) y marcar REVISADA | `resources/views/control/en_vivo.blade.php` + `PATCH /control-ingreso/situaciones/{situacion}/revisar` (ruta `ingreso.situaciones.revisar`) → `revisar()` |

## Reglas
- **Detalle obligatorio**: entre 5 y 1000 caracteres. El botón "Guardar situación" queda desactivado hasta completar lo requerido.
- **Ambiente destino obligatorio solo en `CAMBIO_AMBIENTE`** y debe existir; no puede ser igual al ambiente actual del estudiante.
- **Ambiente origen**: no lo envía el navegador. Se toma del ambiente donde ingresó el estudiante (tabla `ingreso`) o, si aún no ingresó, del ambiente del examen.
- **Estudiante**: opcional, salvo en `CAMBIO_AMBIENTE` y `ESTUDIANTE_NO_HABILITADO`, donde es obligatorio. Se puede indicar por código universitario o por cédula.
- **Usuario, hora y estado no se reciben del navegador**: `registrado_por`, `fecha_hora` y `estado` se ignoran si llegan en la petición. El estado inicial siempre es `PENDIENTE`.
- **REVISADA**: se guarda quién la revisó y cuándo (`revisada_por`, `revisada_at`). Es idempotente: si ya estaba revisada no se sobrescribe el primer revisor.
- **Quién puede**: roles administrador, control y docente; el docente solo en exámenes de sus materias (`Examen::permiteA`, HTTP 403 si no).

## Qué NO se tocó
`VerificacionController`, `ControlEnVivoController`, `IngresoService`, `IngresoController` y las migraciones/modelos de otros RQ. En `verificacion/index.blade.php` solo se agregó el botón, el aviso, el `include` del modal y su JS (y se cambió el texto que decía que el cambio de ambiente "se gestiona en RQ21"). En `en_vivo.blade.php` solo se activó la pestaña "Situaciones" y su lógica.

## Cómo probar
1. `php artisan migrate` (crea la tabla `situacion_excepcional`).
2. Entra como control o administrador → **Verificación**. Elige un examen y verifica a un estudiante.
3. Botón **Registrar situación excepcional** (arriba a la derecha). Sin examen elegido avisa "Selecciona el examen primero".
4. En el modal prueba: `CAMBIO_AMBIENTE` (aparece "Ambiente destino"), `ESTUDIANTE_NO_HABILITADO` (exige estudiante), `PROBLEMA_IDENTIFICACION` y `OTRA`. Al guardar aparece el aviso con la hora y un ítem en el historial.
5. **Control en vivo** → pestaña **Situaciones (n)** (n = pendientes). Verás hora, categoría, estudiante, detalle (con "Ambiente: origen → destino" en cambios de ambiente) y quién la registró. Hay filtro por categoría y buscador.
6. Botón **Marcar revisada** → pasa a REVISADA con "por <usuario> · fecha"; el contador baja. Si falla, el botón dice **Reintentar**.
7. En la base: `SELECT categoria, estudiante_id, ambiente_origen_id, ambiente_destino_id, registrado_por, fecha_hora, estado, revisada_por FROM situacion_excepcional;`
8. Prueba automática: `php artisan test --filter=Rq21` (usa transacciones: no deja datos en tu base).
