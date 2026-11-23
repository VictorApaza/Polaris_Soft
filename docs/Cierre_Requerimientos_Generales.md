# Cierre de requerimientos generales (todo excepto RQ25)

Completa lo que faltaba de los requerimientos generales del proyecto (sección 2.3 del documento de la convocatoria).

## 1. Normas particulares por estudiante (requerimiento general 2)

Las normas **generales** del examen ya existían (`normas_admision` y `normas_salida`). Ahora cada estudiante puede tener además una **norma particular** en un examen concreto (por ejemplo, «puede usar calculadora científica» o «tiene 30 minutos adicionales»).

- **Quién la registra:** el administrador y el docente (este último solo en los exámenes de sus materias), desde **Exámenes → Habilitados → botón «Norma»** de cada estudiante. Si se deja el texto vacío, la norma se elimina.
- **Quién la ve:** en la pantalla de habilitados (debajo del motivo) y, sobre todo, el **personal de control al verificar el ingreso**: aparece en un aviso amarillo, junto con las normas de admisión y salida del examen (avisos azules).
- **Datos:** tabla nueva `norma_particular` (migración `2026_11_22_000001`), una norma por estudiante y examen, máximo 1000 caracteres. Es independiente de la habilitación: no se pierde al habilitar, inhabilitar o quitar la decisión.
- **Historial:** guardar una norma queda en el historial de operaciones («Guardar norma particular»).

### Prueba manual
1. Entra como administrador → Exámenes → Habilitados de un examen → «Norma» de un estudiante → escribe un texto → Guardar. Debe verse bajo su motivo.
2. Entra como personal de control, elige ese examen y verifica a ese estudiante (debe estar habilitado y con foto): se muestra el aviso amarillo con su norma y los azules con las normas del examen.
3. Vuelve a «Norma», borra el texto y guarda: el aviso desaparece.

## 2. Prueba de carga del registro de ingreso

Requisito de contexto: procesar muchos ingresos en periodos cortos sin tiempos de espera excesivos.

```bash
php artisan sciem:prueba-carga --estudiantes=5000
```

Simula la llegada de N estudiantes (50 a 20 000) y, para cada uno, hace lo mismo que el personal de control: **verificar identidad** y **registrar ingreso**. Mide p50/p95/p99 de cada operación, el rendimiento (estudiantes por minuto), los tiempos de las listas del Control en vivo con el examen lleno, y comprueba que **un segundo ingreso se rechaza siempre**. Termina con APROBADA / NO APROBADA (código de salida 0 o 1).

- Opciones: `--umbral-ms=500` (límite de p95, por defecto 500 ms) y `--force` (obligatorio si `APP_ENV=production`).
- **No deja datos:** todo corre en una transacción que siempre se revierte. Aun así consume recursos reales: no la ejecutes en horario de exámenes.
- Resultado de referencia (equipo de desarrollo, MariaDB local, 5 000 estudiantes): p95 de 5,6 ms al verificar y 6,9 ms al registrar el ingreso; 7 122 estudiantes por minuto en un solo puesto; Control en vivo con 5 000 ingresos: 63 ms (ingresados) y 46 ms (pendientes). Los tiempos reales en el servidor del laboratorio dependerán de su hardware: ejecútala allí antes del examen y guarda la captura como evidencia.
- Alcance: mide el procesamiento de la aplicación y la base de datos en un solo puesto. No mide la red ni muchos puestos de control a la vez; para eso, ejecútala también desde varios celulares con el sistema real (la regla «un solo ingreso por estudiante» está garantizada por un índice único en la base de datos).

## 3. Disponibilidad

El sistema ahora expone **`GET /salud`** (público, sin datos sensibles): responde `200 {"estado":"ok"}` si la aplicación y la base de datos responden, y `503` si la base de datos no contesta. Úsalo con una herramienta de monitoreo (UptimeRobot, Zabbix) o con un cron:

```bash
* * * * * curl -fsS --max-time 5 https://TU-SERVIDOR/salud >/dev/null || echo "SCIEM no responde" | mail -s "Alerta SCIEM" tu@correo
```

La disponibilidad real depende del servidor (energía, red, Apache/PHP-FPM y MySQL activos). Recomendaciones para el día del examen: probar el sistema una hora antes, tener un respaldo reciente (Sistema → Respaldos) y mantener `/salud` monitoreado.
