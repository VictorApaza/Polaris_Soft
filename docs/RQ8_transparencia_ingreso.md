# RQ8 · Transparencia de ingreso (HU-011) — Responsable: Laura

Registra, de forma **inmutable**, una instantánea de cada ingreso autorizado (examen, ambiente, controlador, hora y método) y permite a supervisión consultarla en tiempo real.

## Qué incluye (las 3 tareas del Sprint 2)

| Tarea | Dónde está |
|---|---|
| Estructura de datos `transparencia_ingreso` | `database/migrations/2026_10_10_000001_create_transparencia_ingreso_table.php` + `app/Models/TransparenciaIngreso.php` |
| Servicio backend (hora del servidor + metadatos de acceso) | `app/Services/TransparenciaIngresoService.php` + endpoint `POST /transparencia/autorizar` |
| Vista de auditoría en tiempo real | `GET /transparencia` (`resources/views/transparencia/index.blade.php`) + `GET /transparencia/datos` (JSON) |

Acceso: roles `administrador` y `control` (menú lateral → **Transparencia**). La vista es de solo lectura.

## Tabla `transparencia_ingreso`

| Columna | Descripción |
|---|---|
| `id` | PK |
| `estudiante_id`, `examen_id`, `ambiente_id`, `controlador_id` | Vínculos (`ambiente_id` puede ser null si el nombre no está en el catálogo) |
| `metodo_verificacion` | `CODIGO_UNIV` \| `CI` \| `QR` |
| `fecha_hora_ingreso` | Hora del **servidor** (nunca del navegador) |
| `estudiante_codigo`, `estudiante_nombre`, `examen_descripcion`, `ambiente_nombre`, `controlador_nombre` | Instantánea legible al momento de autorizar |
| `ip_origen`, `user_agent` | Metadatos de acceso |

- **Inmutable**: el modelo lanza excepción en `update`/`delete`, y en MySQL hay triggers que rechazan `UPDATE`/`DELETE` directos en la base.
- Sin claves foráneas a propósito (bitácora de auditoría: no debe borrarse ni bloquearse si se elimina un examen o estudiante).

## Cómo registrar una autorización (para RQ5, RQ6 y RQ7)

Cuando el controlador presiona **"Autorizar ingreso"** tras una verificación exitosa, llamar **una sola vez**:

**Desde JavaScript dentro de una vista Blade (misma sesión, con CSRF):**

```js
const respuesta = await fetch(@json(route('transparencia.registrar')), {
    method: 'POST',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    },
    credentials: 'same-origin',
    body: JSON.stringify({
        estudiante_id: 12,        // id_estudiante
        examen_id: 3,
        metodo: 'QR',             // 'CODIGO_UNIV' | 'CI' | 'QR'
        // ambiente_id: 5,        // opcional: si no se envía, se usa el ambiente del examen
    }),
});
const data = await respuesta.json();   // 201 {mensaje, registro} | 422 {mensaje} / {errors}
```

La hora, el controlador (usuario logueado) y la IP/navegador **no se envían**: los captura el servidor.
Respuesta **422** si el estudiante no está habilitado (o está inhabilitado) para ese examen.

**Desde PHP (otro controlador):**

```php
use App\Services\TransparenciaIngresoService;

app(TransparenciaIngresoService::class)
    ->registrar($estudiante, $examen, $request->user(), 'CODIGO_UNIV', $request);
```

## Datos de ejemplo (solo desarrollo)

```
php artisan db:seed --class=TransparenciaIngresoSeeder
```

## Nota sobre la zona horaria

`config/app.php` tiene `'timezone' => 'UTC'`. La base guarda la hora en UTC y la vista la **muestra en hora de Bolivia** (`America/La_Paz`). Si el equipo decide cambiar `timezone` a `America/La_Paz`, la vista se adapta sola.
