# Sprint 5 · RQ26 — Mecanismos de respaldo (backup) de la información registrada

El administrador puede **crear, descargar, verificar, restaurar y eliminar respaldos** desde **Sistema → Respaldos** (`/respaldos`). Además el sistema hace **un respaldo automático cada día**.

## Qué guarda un respaldo
Un solo archivo `respaldo-AAAAMMDD-HHMMSS-<tipo>.zip` en `storage/app/backups` (carpeta privada, no se sirve por la web) con:
- `base_de_datos.sql`: **toda** la base (estructura, datos y los triggers de inmutabilidad de `ingreso` y `transparencia_ingreso`). Se arma con PDO: **no necesita `mysqldump`** instalado en el servidor.
- `archivos/…`: las fotos de estudiantes y las evidencias de incidencias (`fotos_estudiantes`, `incidencias`).
- `manifest.json`: fecha, quién lo creó, tablas y cuántas filas tenía cada una.
- Junto al zip, un `.sha256` con su huella para detectar archivos dañados o alterados.

Tipos: **Manual** (botón), **Automático** (programado) y **Previo a restauración** (copia de seguridad que el sistema hace solo antes de restaurar).

## Criterio de aceptación: «ejecutar un respaldo y restaurarlo, verificando la integridad de los datos»
1. **Crear respaldo ahora** → aparece en la lista con sus tablas, filas, archivos y tamaño.
2. **Verificar** → comprueba la huella SHA-256, que el zip no esté dañado, que traiga la base de datos y que la cantidad de archivos coincida con el manifiesto. No toca la base.
3. **Restaurar** → pide escribir `RESTAURAR`, **verifica el respaldo antes de empezar**, guarda una copia previa del estado actual, repone la base y las fotos y **cuenta las filas de cada tabla comparándolas con el manifiesto**. Si todo coincide muestra «Restauración completa y verificada»; si hay diferencias las lista y recuerda con qué respaldo volver atrás.

## Respaldo automático
- Cada día a las **02:00** (`SCIEM_RESPALDO_HORA`), con `php artisan sciem:respaldar` programado en `app/Console/Kernel.php`.
- Requiere el cron de Laravel en el servidor (el mismo que ya usa «inhabilitar sin foto»): `* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1`
- **Retención**: los automáticos de más de 14 días se borran solos (`SCIEM_RESPALDO_CONSERVAR_DIAS`; siempre quedan los 3 últimos; `0` = nunca borrar). Los manuales y los previos a una restauración no se borran solos.
- Otros ajustes en `config/sciem.php`: `respaldo_con_archivos` y `respaldo_carpetas`.

## Terminal (si el sistema no abre)
```
php artisan sciem:respaldar --tipo=manual         # crea un respaldo
php artisan sciem:restaurar                        # lista los respaldos
php artisan sciem:restaurar respaldo-20261121-020000-automatico   # restaura (pide confirmación; --force la omite)
```

## Reglas y cuidados
- **Solo el administrador**. Los nombres de archivo se validan (`respaldo-AAAAMMDD-HHMMSS[-tipo]`) y las rutas dentro del zip se revisan al restaurar.
- Restaurar **reemplaza toda la información actual** (incluidos usuarios y contraseñas). Conviene hacerlo sin que nadie use el sistema; si la cuenta del administrador no existe en el respaldo, habrá que volver a iniciar sesión.
- Dos respaldos o restauraciones no corren a la vez (cerrojo).
- El respaldo contiene datos personales y fotos: **guarda los archivos descargados en un lugar seguro**. Conviene además copiar periódicamente `storage/app/backups` fuera del servidor (un respaldo en el mismo disco no protege si el disco falla).
- Cada acción queda en el **Historial de operaciones** (Crear / Verificar / Restaurar / Eliminar respaldo).

## Dónde está el código
| Pieza | Archivo |
|---|---|
| Crear, verificar, restaurar, retención | `app/Services/RespaldoService.php` |
| Pantalla del administrador | `app/Http/Controllers/RespaldoController.php` + `resources/views/respaldos/index.blade.php` |
| Comandos | `app/Console/Commands/Respaldar.php`, `RestaurarRespaldo.php` |
| Programación diaria | `app/Console/Kernel.php` |
| Ajustes | `config/sciem.php` |
| Rutas | `routes/web.php` (grupo administrador, prefijo `/respaldos`) |
| Pruebas | `tests/Feature/Sprint5RespaldoTest.php` |

## Qué NO se tocó
Ninguna migración ni tabla: el respaldo solo **lee** la base. Los cambios en archivos existentes son mínimos: una línea en el programador de tareas, tres claves nuevas en `config/sciem.php`, cuatro etiquetas en el Historial de operaciones, el enlace «Respaldos» en el menú del administrador y los iconos nuevos (`i-download`, `i-database`, `i-refresh`).

## Cómo probar
1. `php artisan test --filter=Sprint5Respaldo` (usa un disco simulado; no crea archivos en `storage/app/backups`).
2. Como administrador: **Sistema → Respaldos → Crear respaldo ahora**, luego **Verificar** y **Descargar**.
3. Prueba de restauración **en una copia de la base, no en la real**: crea un respaldo, borra o cambia algo (p. ej. elimina un ambiente), **Restaurar** → escribe RESTAURAR y comprueba que el dato volvió y que sale «Restauración completa y verificada».
4. Programación: `php artisan schedule:list` debe mostrar `sciem:respaldar` a las 02:00.
