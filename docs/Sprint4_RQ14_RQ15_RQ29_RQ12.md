# Control en vivo (RQ14 y RQ15) + piezas pendientes de RQ12 y RQ29

## RQ14 — Consultar en tiempo real quiénes ya ingresaron
| Tarea del backlog | Dónde quedó |
|---|---|
| GET `/control-ingreso/examen/{examen}/ingresados` (nombre, código, CI, ambiente, fecha_hora_ingreso, si fue expulsado, total y `?desde=`) | `ControlEnVivoController::ingresados()` |
| Índice (examen_id, fecha_hora_ingreso) para responder en menos de 1 s con 2.000 estudiantes | ya existe en `ingreso`; la migración `2026_11_06_000001` lo asegura |
| Vista `GET /control-ingreso/examen/{examen}/en-vivo` con selector de examen, contadores y pestañas Ingresaron / Pendientes / Expulsados / Situaciones | `resources/views/control/en_vivo.blade.php` (menú lateral: "Control en vivo") |
| Pestaña "Ingresaron" se actualiza cada 5 s sin recargar, solo con los ingresos nuevos, y aviso con botón "Reintentar" si falla la conexión | JS de la misma vista |
| Buscador por nombre, código o CI y filtro por ambiente | JS de la misma vista |

## RQ15 — Consultar quiénes aún no se presentaron
| Tarea | Dónde quedó |
|---|---|
| GET `/control-ingreso/examen/{examen}/pendientes` (habilitados sin ingreso + totales habilitados/ingresaron/pendientes) | `ControlEnVivoController::pendientes()` |
| Pestaña "Pendientes" con actualización cada 5 s; quien registra su ingreso desaparece de la lista | vista en vivo |
| Resumen Habilitados / Ingresaron / Pendientes con buscador (nombre, código, CI) y filtro por grupo | vista en vivo |

Las pestañas **Expulsados** (RQ20) y **Situaciones** (RQ21) están visibles pero desactivadas: las completan Laura y Eduardo.
Cuando exista la tabla `expulsion`, "Ingresaron" ya marca como EXPULSADO a quien figure en ella y el contador de expulsados se llena solo.

## RQ12 (pieza que faltaba)
GET `/intentos-ingreso/examen/{examen}` → intentos rechazados del examen (filtros `?motivo=`, `?desde=`, `?limite=`) con totales por motivo. `IntentoIngresoController`.

## RQ29 (piezas que faltaban)
- Carga por el personal (admin o docente de sus estudiantes): `/fotos/cargar` → individual por código universitario y ZIP (cada archivo = `codigo.jpg|png`); el resultado lista los archivos no asociados con el motivo. `FotoCargaController`.
- Consulta de la foto por código, CI o QR: `GET /fotos/consultar?codigo_universitario=… | ci=… | qr=…` (imagen; con `&formato=json` devuelve datos y URL).
- Solo JPG/PNG, máx. 2 MB, mínimo 200x200, disco privado.

## Cómo probar
1. `php artisan migrate`  (migración nueva de índices)
2. `php artisan test --filter=Sprint4`
3. Menú "Control en vivo": elige un examen; en otra pestaña autoriza un ingreso en Verificación y mira cómo aparece solo en ≤ 5 s y baja de Pendientes.
4. Apaga `php artisan serve` un momento: debe salir el aviso amarillo con "Reintentar".
5. Estudiantes › "Cargar fotos": prueba un ZIP con `202400034.jpg` y otro archivo mal nombrado.
