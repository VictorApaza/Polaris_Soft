# Sprint 2 · Qué se implementó (y cómo probarlo)

| RQ | Estado | Dónde |
|---|---|---|
| RQ3 Habilitar estudiantes | Ya estaba; ahora **también el docente** | `HabilitacionController`, `/examenes/{id}/habilitados`, `/docente/examenes` |
| RQ4 Motivos de inhabilitación | Ya estaba; ahora **también el docente** | mismo módulo |
| RQ5 Verificación por código | Ya estaba; ahora guarda bitácora | `VerificacionController::verificar` |
| RQ6 Verificación por CI | Ya estaba; ahora guarda bitácora e **intentos** | tablas `verificacion` e `intento_ingreso` |
| RQ7 Verificación por QR | **Nuevo** | `QrEstudianteService`, `EstudianteQrController`, pantalla `/verificacion` |
| RQ8 Transparencia | Ya estaba; se agregó índice único (RQ11) | `transparencia_ingreso` |
| RQ28 Despliegue | **Nuevo** `deploy.sh` y `.env.example` | raíz del proyecto |

## Extras pedidos
- **Correo con credenciales:** al crear un usuario (o cambiarle la contraseña) se le envía un correo con su acceso. `app/Mail/CredencialesUsuario.php`.
- **Docente habilita/inhabilita:** menú "Habilitar estudiantes" para el rol docente.
- **Facultad en materias:** columna, formulario (la carrera se filtra según la facultad) y listado.

## QR (RQ7)
- El QR contiene un token **cifrado con APP_KEY**, no el código en claro. Si se altera, se rechaza ("Código QR no válido o alterado").
- Si cambia el código universitario del estudiante, su QR viejo queda caducado.
- **Si se cambia APP_KEY, todos los QR ya enviados dejan de servir.**
- Envío: en *Exámenes → Habilitados* hay un botón "Enviar QR por correo a los habilitados". Cada estudiante recibe un enlace firmado (30 días) a su QR.
- El administrador puede ver/imprimir el QR de cualquiera desde *Estudiantes* (ícono de carnet).
- Escaneo: cámara (celular/tablet/webcam), lector USB (escribe en el campo) o digitando el token. Beeps de éxito/error con Web Audio.
- La cámara del celular exige **HTTPS** (o localhost).

## Pruebas manuales sugeridas
1. `php artisan migrate` (crea `verificacion`, `intento_ingreso`, `facultad` en materias, índice único).
2. Con `MAIL_MAILER=log`, crear un usuario y revisar `storage/logs/laravel.log`: debe estar el correo con la contraseña.
3. Entrar como docente (maria.ramos@sciem.edu / password123) → "Habilitar estudiantes" → habilitar e inhabilitar con motivo.
4. Como administrador: Estudiantes → ícono QR → copiar el contenido y pegarlo en Verificación con método "Código QR".
5. Verificar un código inexistente y un estudiante inhabilitado y mirar la tabla `intento_ingreso`.
6. Registrar ingreso dos veces: la segunda debe mostrar "ya registró ingreso".

## Pendiente / decisiones
- RQ3: la "nota de excepción" al asociar a mano un estudiante que no es de la materia (HU-006) no se implementó.
- El docente ve **todos** los exámenes: no hay vínculo entre la cuenta de usuario y la ficha de `docentes`, por eso no se filtra por "sus" materias.
- RQ28: el despliegue real en WEBTIS depende de acceso al servidor; aquí queda el script y la guía.

## Correcciones del informe de QA (HU001, HU003, HU007, HU008)

| Bug | Qué pasaba | Corrección |
|---|---|---|
| 1-1 Asterisco alejado | `.field label` repartía el espacio entre texto y `*` | `public/css/sciem.css`: el `*` queda pegado a la palabra |
| 1-2 Nombres "mIxTeD cAsE" | Se guardaba tal cual se escribía | `Texto::nombrePropio()` (Guevara, María de la Cruz) al registrar, editar e importar CSV de estudiantes; también docentes y usuarios |
| 2-1 Carrera vacía en Registrar examen | Las carreras salen de `estudiante_asignatura`, que nadie llenaba | `InscripcionAsignaturaService` inscribe al asignar materia (y al importar CSV), migración que rellena lo ya existente, y el seeder lo llama. Si no hay carreras, el formulario lo explica |
| 7-1 Modal Inhabilitar pierde datos al hacer clic afuera | El modal no registraba sus valores iniciales y el sistema creía que no había cambios | Se registran al abrirlo; además Esc pide confirmación si hay datos escritos |
| 7-2 Error 500 `id_habilitacion` | Bases creadas con migraciones tenían la columna `id` | Migración `2026_10_17_000001` renombra `id` → `id_habilitacion` |
| 8-1 Resultado de verificación apilado | — | Ya estaba como tarjeta con rejilla de 2 columnas |
| 8-2 Foto sin sentido en Verificación | — | Ya estaba eliminada |

Después de actualizar: `php artisan migrate`.

## Ampliación: portal del estudiante, fotos, incidencias y reportes

Ver `docs/Flujo_antes_del_examen.md` (qué hace cada rol). Resumen técnico:

- **Portal del estudiante** `/estudiante/login` (código universitario + cédula, con límite de intentos): aula, QR y foto.
- **Fotos**: se guardan en `storage/app/fotos_estudiantes` (privadas, se sirven con sesión). Estados: sin foto / pendiente / aprobada / observada.
- **Incidencias** (tabla `incidencia`): intento de ingreso repetido (automática, con hora del intento y del primer ingreso), foto no coincide, suplantación, trampa, otra.
- **Reportes** `/reportes`: entraron, no entraron, inhabilitados por motivo, incidencias, estado de fotos.
- **Buscador de estudiantes** `/buscar-estudiantes` (docente y administrador).
- **Verificación rediseñada**: pestañas grandes (código, CI, QR), foto y datos, alertas, historial de la sesión.
- **Accesos al sistema** (tabla `acceso_log`): `/transparencia/accesos`, solo administrador.
- Migración: `2026_10_20_000001_portal_estudiante_fotos_incidencias_accesos.php`. Después: `php artisan migrate`.
