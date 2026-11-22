# Sprint 5 · RQ16, RQ17, RQ18 y RQ19 — Reportes del examen (PDF y Excel)

Cuatro reportes por examen para el **administrador** y el **docente del examen**. Todos se ven en pantalla con el mismo diseño del resto del sistema y se descargan en **PDF** o **Excel (.xlsx)** con los botones de arriba a la derecha (o con `?formato=pdf` / `?formato=xlsx` en la dirección).

| RQ | Reporte | Pantalla | Qué contiene |
|---|---|---|---|
| RQ16 | Asistencia | `GET /reportes/examen/{examen}/asistencia` (`reportes.asistencia`) | Los estudiantes que ingresaron: hora, código, cédula, método (código / CI / QR), aula y quién registró el ingreso; marca a los expulsados (RQ20). Resumen: habilitados, ingresaron, ausentes, expulsados y % de asistencia. |
| RQ17 | Estudiantes ausentes | `GET /reportes/examen/{examen}/ausentes` (`reportes.ausentes`) | Habilitados **sin ingreso registrado** (cruce `habilitacion` × `ingreso`), con código, cédula, carrera, correo y estado de la foto. |
| RQ18 | Horarios de ingreso y estudiantes registrados | `GET /reportes/examen/{examen}/horarios` (`reportes.horarios`) | Ingresos por franja de 5, 10, 15, 30 o 60 min (con acumulado y %), primer y último ingreso, hora pico, cuántos llegaron antes/después del inicio y la llegada promedio; ingresos por aula; y un comparativo de registrados con los últimos exámenes (útil para planificar). |
| RQ19 | Intentos a un examen que no corresponde | `GET /reportes/examen/{examen}/intentos-indebidos` (`reportes.intentos`) | Fecha, hora, estudiante, dato ingresado, aula, controlador, número de intento (resalta a los reincidentes) y el examen al que **sí** le corresponde entrar. |

Se llega desde **Reportes → Ver reporte** de un examen: ahí hay un bloque «Reportes para descargar en PDF o Excel».

## Criterios de aceptación → cómo se cumplen
- **RQ16 «coincide con los ingresos registrados»**: la lista sale de la tabla `ingreso` (la misma del Control en vivo), una fila por ingreso. El reporte muestra un cuadro «Cuadra con los registros: X habilitados que ingresaron + Y ausentes = Z habilitados».
- **RQ17 «cruza habilitación e ingreso»**: `habilitacion.estado = 'habilitado'` y sin fila en `ingreso` para ese examen. Los inhabilitados **no** cuentan como ausentes (se aclara en pantalla).
- **RQ18 «exactitud»**: la suma de las franjas es siempre igual a la cantidad de registrados, con cualquier tamaño de franja.
- **RQ19 «coincide con RQ12»**: usa los registros de `intento_ingreso` con motivo `EXAMEN_NO_CORRESPONDE` (la misma constante que usa RQ12) y trae un enlace a la consulta JSON original, que da el mismo total.

## Dónde está el código
| Pieza | Archivo |
|---|---|
| Consultas de los 4 reportes (solo lectura) | `app/Services/ReporteExamenService.php` |
| Pantallas y descargas | `app/Http/Controllers/ReporteExamenController.php` |
| PDF y Excel sin librerías externas | `app/Support/ExportadorTabla.php` |
| Vistas | `resources/views/reportes/asistencia·ausentes·horarios·intentos.blade.php` + `_encabezado` y `_paginador` |
| Rutas | `routes/web.php` (grupo administrador + docente, junto a `reportes.show`) |
| Pruebas | `tests/Feature/Sprint5ReportesExamenTest.php` |

## Reglas
- **Permisos**: administrador y docente (el docente solo en exámenes de sus materias, `Examen::permiteA`). El personal de control recibe 403.
- **Horas** en hora de Bolivia (`America/La_Paz`), igual que el resto del sistema.
- En pantalla las listas se paginan de a 50; **el PDF y el Excel traen todas las filas**.
- El PDF es A4 horizontal, con encabezado, cifras clave, tabla con cabecera repetida en cada página y numeración. El Excel tiene la cabecera con color, columnas ajustadas y la fila de títulos congelada.
- `?formato=` distinto de `pdf` o `xlsx` responde 422.

## Qué NO se tocó
`ReporteController` y las pantallas `reportes/index` y las secciones de `reportes/show` quedan igual; solo se agregó el bloque de enlaces a los nuevos reportes en `show`. Tampoco cambian el Control en vivo, la verificación de ingreso ni ninguna tabla: **no hay migraciones nuevas** para estos RQ.

## Cómo probar
1. Entra como administrador (o docente) → **Reportes** → **Ver reporte** de un examen con ingresos.
2. En «Reportes para descargar» abre **Asistencia**: compara la cantidad de filas con **Control en vivo → Ingresaron** del mismo examen.
3. **Ausentes**: compara con **Control en vivo → Pendientes**.
4. **Horarios**: cambia «Agrupar los ingresos cada» y comprueba que el total de «Registrados» no cambia.
5. **Intentos indebidos**: verifica con el control de ingreso a un estudiante de otra materia en ese examen (RQ12) y recarga el reporte; abre «ver la consulta original (JSON)» para comparar el total.
6. En cada uno pulsa **Descargar PDF** y **Descargar Excel** y abre los archivos.
7. Prueba automática: `php artisan test --filter=Sprint5ReportesExamen`.
