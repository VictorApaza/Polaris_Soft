# Demostración en WebTIS

## Alcance y estado

Laravel 10.28, PHP 8.2 y MariaDB 10.11. Entorno académico temporal, datos ficticios y cinco usuarios simultáneos. El código incluye los PR #4 y #5 y correcciones de integración con el Sprint 2.

La verificación local no sustituye la aceptación en TIS. Antes de publicar deben cumplirse:

- Certificado HTTPS válido para `polarissoft.tis.cs.umss.edu.bo`. No aceptar advertencias ni enviar credenciales por HTTP.
- Cuenta SMTP y remitente autorizados, o aceptación explícita del equipo de una demostración sin envío real de correos. El transporte `log` no entrega recuperación de contraseñas, credenciales ni QR.
- Cuota, límite de carga y extracción ZIP suficientes; permiso de escritura solo en `storage/` y `bootstrap/cache/` para el usuario PHP.
- Privilegios de tablas, índices y TRIGGER; comprobar que existen los dos triggers de auditoría y el índice único de ingreso.
- Copia de archivos existentes, base y configuración antes de modificar el servidor.

El 18 de octubre se verificaron PHP 8.2.28, MariaDB 10.11.11, acceso a FTP y phpMyAdmin mediante HTTPS en el dominio principal de TIS, y una base vacía. El subdominio de la aplicación presentaba un error de certificado. El equipo informó que no tiene SMTP.

Laravel 10 terminó su soporte de seguridad. El lock conserva dependencias con avisos de seguridad. Una actualización de dependencias dentro de Laravel 10 fue bloqueada por la política de Composer. No se desactiva esa política ni se presenta esta entrega como apta para datos reales o exposición permanente. El equipo debe tratar la actualización a una versión soportada como trabajo pendiente.

## Entorno local

Crear `.env` privado con APP_KEY y contraseñas aleatorias. No copiar credenciales de TIS al entorno de pruebas. La base local debe llamarse `polaris_demo`, con host `db`. Añadir `DB_ROOT_PASSWORD` solo al archivo local de Compose.

```powershell
docker compose --env-file .env -f deployment/compose.yaml up -d --build
docker compose --env-file .env -f deployment/compose.yaml exec -T web composer install --no-interaction --prefer-dist
docker compose --env-file .env -f deployment/compose.yaml exec -T web php artisan migrate --force
docker compose --env-file .env -f deployment/compose.yaml exec -T web php deployment/seed-demo.php
```

El preparador rechaza una base que ya contiene usuarios. Crea contraseñas independientes y guarda los accesos solo en `storage/app/demo-access.json`. La aplicación está en `http://127.0.0.1:8018` y el buzón local en `http://127.0.0.1:8025`. Mailpit captura mensajes; no envía correo a Internet.

Las pruebas con RefreshDatabase deben usar una base separada `polaris_test` con permisos para el usuario local:

```powershell
docker compose --env-file .env -f deployment/compose.yaml exec -T -e APP_ENV=testing -e DB_DATABASE=polaris_test -e SESSION_DRIVER=array -e CACHE_DRIVER=array -e MAIL_MAILER=array web php artisan test --no-ansi
node --test tests/js/asignacion.test.cjs
```

Nunca ejecutar `migrate:fresh`, `db:wipe`, RefreshDatabase ni los preparadores de prueba en TIS.

## Construcción de una entrega

Con el código revisado y guardado en Git:

```powershell
./deployment/build-release.ps1
```

Se conserva cada entrega en `storage/app/releases/`. La exportación usa una lista explícita de rutas de Git y Composer instala desde el lock con `--no-dev`. El paquete contiene `public_html`, vendor y directorios de ejecución vacíos. No contiene `.env`, Git, documentación privada, PDF, Excel, pruebas, registros ni los preparadores locales. `manifest.json` identifica commit y SHA-256.

`initial-database.sql` es privado y se importa únicamente en una base vacía. Contiene usuarios con contraseñas cifradas mediante hash y los datos ficticios preparados. Conservar la APP_KEY usada para crear sus QR. No publicar SQL, accesos ni configuración en `public_html` o Git.

Antes de subir: extraer `application.zip` en otro directorio local, configurar un `.env` privado, restaurar SQL en otra base vacía y comprobar login, asignación, exámenes, habilitación, verificación, auditoría y correos capturados con el vendor de producción. Verificar que el paquete no sirve `.env` ni SQL y que el modo debug está apagado.

## Instalación inicial en TIS

1. Validar HTTPS del subdominio y realizar una copia descargada de los archivos y la base existentes. Conservar `log/`.
2. Entrar mediante `https://tis.cs.umss.edu.bo/net2ftp/` y `https://tis.cs.umss.edu.bo/phpmyadmin/`. Nunca usar la página de acceso con certificado inválido.
3. Comprobar la carpeta raíz y el directorio público. Desplegar primero con acceso de mantenimiento; subir y extraer el paquete en la raíz FTP, no dentro de `public_html`.
4. Dejar `app`, `bootstrap`, `config`, `database`, `resources`, `routes`, `storage` y `vendor` como hermanos de `public_html`. Solo el contenido público debe ser accesible por la web. Retirar la página de diagnóstico PHP después de respaldarla.
5. Crear `.env` fuera de `public_html`, a partir de `env.webtis.example`: APP_KEY privada, URL HTTPS definitiva, DEBUG=false, DB real y SMTP. Mantener permisos restrictivos compatibles con el propietario y el usuario PHP; no aplicar 777.
6. Confirmar otra vez que `polarissoft_db` está vacía. Importar `initial-database.sql` desde phpMyAdmin. Si deja de estar vacía, detenerse y preparar una migración incremental.
7. Comprobar migraciones, claves foráneas, índice `transp_estudiante_examen_unique` y triggers `transparencia_ingreso_no_update` y `transparencia_ingreso_no_delete`. Si no se crean, resolver permisos antes de aceptar la auditoría.
8. Probar por HTTPS con las cuentas ficticias: roles, sesión, asignación y filtros, crear examen, habilitar, validar código/CI/QR, registrar una vez y rechazar duplicado. Probar recuperación de contraseña y recepción real de QR. Revisar logs sin exponerlos al navegador.
9. Verificar respuestas denegadas para `/.env`, `/composer.json`, `/storage/logs/laravel.log` y archivos SQL. Retirar archivos temporales de carga y abrir el acceso solo tras validar.

No crear una ruta web para ejecutar Artisan, migraciones ni comandos del sistema. Si la universidad facilita consola, usarla con su usuario autorizado; si solo hay phpMyAdmin, preparar y ensayar SQL específico por versión.

En TIS, PHP y FTP usan propietarios distintos. La configuración de entrega guarda sesiones y caché en las tablas `sessions`, `cache` y `cache_locks`; mantiene el límite de intentos de acceso entre peticiones. Los registros se envían al error log del servidor. `VIEW_CACHE_PRIVATE_TEMP=true` compila las vistas en una carpeta temporal privada (0700) propiedad de PHP, identificada por aplicación y APP_KEY. Esas vistas son regenerables; su limpieza no elimina datos ni sesiones. No almacenar archivos de usuarios en ese temporal. Se conservan los permisos del código y se evita conceder escritura universal a `storage/`.

## Actualizaciones mientras continúa el desarrollo

- Construir una entrega identificada por commit y revisar compatibilidad de PHP, extensiones y dependencias. Instalar desde el lock, sin actualizar dependencias durante la carga al servidor.
- Ensayar la actualización sobre una copia de la versión y la base actualmente desplegadas. Una instalación desde cero no prueba una actualización.
- Preparar migraciones incrementales para nuevas columnas o tablas, preservando datos. Si no hay consola, revisar un SQL equivalente para esa versión y probarlo en la copia. No importar otra vez el SQL inicial ni sustituir la base existente.
- Activar mantenimiento antes de copiar archivos; guardar respaldo de base, código, `.env` y `storage`. Mantener APP_KEY, sesiones y archivos persistentes. No regenerar APP_KEY al actualizar: invalida firmas QR y datos cifrados.
- Aplicar cambios compatibles con el código anterior cuando sea posible; después cambiar los archivos de la versión y limpiar únicamente cachés de aplicación correspondientes. No subir cachés generadas con rutas de Windows.
- Ejecutar la comprobación funcional y revisar logs. Anotar commit, fecha, copia previa y resultado. Si falla, mantener mantenimiento: restaurar código anterior solo si el esquema sigue siendo compatible; de lo contrario restaurar la copia completa ensayada. Una restauración descarta escrituras posteriores al respaldo.

## Evidencia local

La comprobación actual cubre asignación → inscripción → examen → habilitación → verificación → ingreso → auditoría, permisos, cuentas desactivadas, validaciones de catálogo, CSV, recuperación y uso único del token. La prueba HTTP con cinco sesiones registró exactamente un ingreso en solicitudes simultáneas. QR firmado y alterado se verifican mediante pruebas; una cámara física requiere comprobación en el dispositivo final y HTTPS.

Los resultados locales, el buzón capturado y las capturas no prueban recepción SMTP real ni el despliegue remoto. Registrar esos dos resultados por separado.
