# Qué hace cada rol antes y durante el examen

Plazos configurables en `.env` (valores por defecto):

| Variable | Valor | Significado |
|---|---|---|
| `SCIEM_PORTAL_ABRE_HORAS` | 72 | El portal del estudiante se abre 72 h antes del examen |
| `SCIEM_FOTO_LIMITE_HORAS` | 48 | La foto se puede subir/corregir hasta 48 h antes |
| `SCIEM_ZONA_HORARIA` | America/La_Paz | Zona horaria de las horas de los exámenes |

## Administrador (semanas antes)
1. Registra estudiantes (con su correo institucional), materias, grupos, docentes y ambientes.
2. Asigna materias/grupos a los estudiantes (así quedan inscritos y salen en el examen).
3. Crea el examen (carrera, asignatura, fecha, hora, aula).
4. Crea las cuentas del personal (docentes y control): reciben sus credenciales por correo.
5. Habilita a los estudiantes en *Exámenes → Habilitados* (o lo hace el docente).

## Docente (72 h → examen)
1. **Habilitar estudiantes** → elige el examen y habilita; inhabilita con motivo cuando corresponda.
2. Cuando se abre el portal, entra a **Fotos** del examen y **aprueba** las fotos buenas o las **observa**
   (escribe qué está mal: borrosa, sin rostro…). El estudiante ve la observación y puede subir otra hasta el límite.
3. Al vencer el plazo (48 h antes): botón **"Inhabilitar a quienes no subieron una foto válida"**
   (también se puede automatizar con `php artisan sciem:inhabilitar-sin-foto`, programado cada hora).
4. **Buscar estudiantes** en cualquier momento por nombre, código o cédula.
5. Durante y después: **Reportes** (cuántos entraron, cuántos no, inhabilitados y por qué, incidencias y posibles trampas).
   Desde ahí puede **inhabilitar por trampa** y marcar incidencias como revisadas.

## Estudiante (72 h → examen)
1. Entra a `/estudiante/login` con su **código universitario** y su **cédula**.
2. Ve en qué **aula** rinde, fecha y hora, y si está habilitado.
3. Sube su **foto actual** (JPG/PNG, mínimo 200×200, máx. 2 MB) hasta 48 h antes del examen.
   Si el docente la observa, debe subir otra a tiempo.
4. Ve su **código QR** (solo habilitados) y lo muestra en la puerta junto con su carnet.

## Personal de control (día del examen)
1. **Verificación**: elige el examen y el método (código universitario, cédula o QR).
2. Ve la **foto y los datos** del estudiante y compara con la persona.
3. Verde = habilitado → **Registrar ingreso**. Rojo = no habilitado / no registrado.
4. Si alguien intenta entrar **por segunda vez** con el mismo código/CI/QR: alerta roja con la hora del primer ingreso
   y la hora del intento; queda como **incidencia** automáticamente.
5. Puede reportar a mano: "la foto no coincide", "sospecha de suplantación" u otra incidencia.

## Transparencia
- **Transparencia de ingreso**: cada ingreso autorizado (inmutable).
- **Accesos al sistema** (solo administrador): cada inicio de sesión, correcto o fallido (contraseña incorrecta,
  usuario inexistente, cuenta inactiva, bloqueo), con fecha, hora e IP. Nunca se guarda la contraseña.

## Cron en el servidor (opcional, para la inhabilitación automática)
```
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```
